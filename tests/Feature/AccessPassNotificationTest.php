<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Models\AccessPass;
use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P1-61: AccessPassController::store()/revoke() had zero notification of
 * any kind before this - confirmed by grep (`AccessPassCreated`/
 * `AccessPassRevoked` events, templates and listeners are all new this
 * round, registered in NotificationEvents as access_pass.created/revoked).
 * `revoke()` is Security-group and three-channel because Admin/Super Admin
 * can revoke a pass a *resident* created (`authorizeOwnPass()` allows it) -
 * the resident who owns the pass may not be the one who acted.
 *
 * Email-channel assertions use `Queue::fake()` and inspect the pushed
 * `DispatchEventNotification` job rather than `email_logs` -
 * `->afterCommit()` never actually fires inside `RefreshDatabase`'s own
 * test-wrapping transaction (it rolls back, never truly commits), the same
 * gotcha `DispatchEventNotificationTest`/`NoticePublishedEmailE2ETest`
 * already document and work around. SMS/WhatsApp go straight to their
 * services (no queue), so those *do* show up in `email_logs` directly.
 */
class AccessPassNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function makePass($society, $resident, array $overrides = []): AccessPass
    {
        $flat = $this->makeFlat($society, $this->makeBuilding($society));

        return AccessPass::create(array_merge([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'person_name'  => 'Ramesh Kumar',
            'person_phone' => '9123456780',
            'pass_type'    => 'recurring_staff',
            'access_scope' => 'property',
            'token'        => AccessPass::generateToken(),
            'allowed_days' => [1, 2, 3, 4, 5],
            'valid_from'   => now()->toDateString(),
            'status'       => 'active',
            'created_by'   => $resident->id,
        ], $overrides));
    }

    public function test_creating_a_pass_through_the_real_controller_action_queues_an_email_to_its_creator(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $this->makeRole('Apartment Owner');
        $resident->assignRole('Apartment Owner');

        $response = $this->actingAs($resident)->post(route('access-passes.store'), [
            'flat_id'      => $flat->id,
            'pass_type'    => 'recurring_staff',
            'person_name'  => 'Sunita Devi',
            'person_phone' => '9876501234',
            'allowed_days' => [1, 3, 5],
        ]);

        $response->assertRedirect();

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($resident) {
            return $job->triggerEvent === 'access_pass_created'
                && $job->recipient['email'] === $resident->email
                && $job->societyId === $resident->society_id;
        });
    }

    public function test_revoking_a_pass_queues_an_email_to_the_creator_not_the_revoking_admin(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, 'Villa Owner');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $pass = $this->makePass($society, $resident);

        $this->actingAs($admin)->post(route('access-passes.revoke', $pass))->assertRedirect();

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($resident) {
            return $job->triggerEvent === 'access_pass_revoked'
                && $job->recipient['email'] === $resident->email;
        });
        Queue::assertNotPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($admin) {
            return $job->recipient['email'] === $admin->email;
        });
    }

    public function test_revoke_sends_sms_and_whatsapp_when_those_channels_are_configured(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, 'Tenant', ['phone' => '9988776655']);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $pass = $this->makePass($society, $resident);

        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY', 'encrypted', $society->id);
        SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_template_id', '169ec8e6c8', 'string', $society->id);
        SmsService::$transportOverride = fn () => [['return' => true], 200];

        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'TEST-KEY', 'encrypted', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '123456', 'string', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_access_pass_template_name', 'access_pass_update', 'string', $society->id);
        WhatsAppService::$transportOverride = fn () => [['messages' => [['id' => 'wamid.test']]], 200];

        $this->actingAs($admin)->post(route('access-passes.revoke', $pass))->assertRedirect();

        $this->assertDatabaseHas('email_logs', [
            'channel'         => 'sms',
            'recipient_phone' => '9988776655',
            'trigger_action'  => 'notification',
            'status'          => 'sent',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'channel'         => 'whatsapp',
            'recipient_phone' => '9988776655',
            'trigger_action'  => 'access_pass',
            'status'          => 'sent',
        ]);
    }

    public function test_revoking_a_pass_in_one_society_never_queues_an_email_to_another_societys_resident(): void
    {
        Queue::fake();

        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $residentA = $this->makeUserWithRole($societyA, 'Tenant');
        $residentB = $this->makeUserWithRole($societyB, 'Tenant');
        $adminA = $this->makeUserWithRole($societyA, 'Admin');
        $passA = $this->makePass($societyA, $residentA);

        $this->actingAs($adminA)->post(route('access-passes.revoke', $passA))->assertRedirect();

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($residentA, $societyA) {
            return $job->recipient['email'] === $residentA->email && $job->societyId === $societyA->id;
        });
        Queue::assertNotPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($residentB) {
            return $job->recipient['email'] === $residentB->email;
        });
    }
}
