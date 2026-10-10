<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Found while building NotificationConfigIntegrityTest, not from a bug
 * report: ServiceController::assignRequest/updateRequestStatus (real,
 * already-dispatched call sites) call
 * EmailNotificationService::sendServiceRequestNotification(), which looks
 * up `service-request-{assigned,in_progress,completed,cancelled}` by slug.
 * None of those four templates had ever been seeded (2026_10_07_000006
 * fixed that), and no resident role was permitted to receive them in
 * shouldReceiveEmail() either - a second, independent silent-no-op on the
 * same feature, fixed by adding the 'service-request-*' wildcard
 * (shouldReceiveEmail() already supported fnmatch() patterns; nothing used
 * one for this prefix) to all four resident roles. This is the same two-bug
 * shape BillReminderCommandTest pins for bill-due-reminder: one test
 * through the real controller action, one isolating sendByTrigger directly.
 */
class ServiceRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeServiceRequest(string $role = 'Apartment Owner'): ServiceRequest
    {
        $society = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, $role);
        $service = Service::create([
            'name' => 'Maid Service ' . uniqid(),
            'category' => 'daily',
            'is_enabled' => true,
        ]);

        return ServiceRequest::create([
            'society_id'  => $society->id,
            'user_id'     => $resident->id,
            'service_id'  => $service->id,
            'description' => 'Daily cleaning',
            'status'      => 'pending',
        ]);
    }

    public function test_assigning_a_request_sends_without_the_previous_silent_no_op(): void
    {
        Mail::fake();

        $serviceRequest = $this->makeServiceRequest();

        $result = \App\Services\EmailNotificationService::sendServiceRequestNotification($serviceRequest, 'assigned');

        $this->assertTrue($result);
        $this->assertDatabaseHas('email_logs', [
            'trigger_action' => 'service-request-assigned',
            'status'         => 'sent',
        ]);
    }

    public function test_every_service_request_lifecycle_action_sends_to_the_requesting_resident(): void
    {
        Mail::fake();

        foreach (['assigned', 'in_progress', 'completed', 'cancelled'] as $action) {
            $serviceRequest = $this->makeServiceRequest();

            $result = \App\Services\EmailNotificationService::sendServiceRequestNotification($serviceRequest, $action);

            $this->assertTrue($result, "sendServiceRequestNotification('{$action}') returned false.");
            $this->assertDatabaseHas('email_logs', [
                'trigger_action' => "service-request-{$action}",
                'status'         => 'sent',
            ]);
        }
    }

    public function test_the_real_controller_action_sends_the_email_end_to_end(): void
    {
        Mail::fake();

        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $resident = $this->makeUserWithRole($society, 'Villa Owner');
        $service = Service::create(['name' => 'Cook ' . uniqid(), 'category' => 'daily', 'is_enabled' => true]);

        $provider = \App\Models\ServiceProvider::create([
            'society_id'      => $society->id,
            'service_id'      => $service->id,
            'name'            => 'Test Provider',
            'contact_number'  => '9123456789',
            'availability'    => 'available',
            'status'          => 'active',
        ]);

        $serviceRequest = ServiceRequest::create([
            'society_id'  => $society->id,
            'user_id'     => $resident->id,
            'service_id'  => $service->id,
            'description' => 'Weekly cooking',
            'status'      => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('service-requests.assign', $serviceRequest), ['assigned_provider_id' => $provider->id])
            ->assertRedirect();

        $this->assertDatabaseHas('email_logs', [
            'trigger_action' => 'service-request-assigned',
            'status'         => 'sent',
            'recipient_email' => $resident->email,
        ]);
    }
}
