<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Models\Facility;
use App\Models\FacilityBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * P1-61: `FacilityBookingController::updateStatus()` (Admin-only) had no
 * notification of any kind before this - confirmed by grep across the
 * controller finding zero `EmailNotificationService`/`DynamicEmailService`/
 * `::dispatch(` call sites anywhere in it. A resident's booking could be
 * approved, rejected or cancelled with nothing telling them so.
 */
class FacilityBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function grantManageFacilities($user): void
    {
        Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $user->givePermissionTo('manage_facilities');
    }

    private function makeBooking($society, $user, $flat): FacilityBooking
    {
        $facility = Facility::create([
            'society_id' => $society->id,
            'name'       => 'Clubhouse',
            'type'       => 'clubhouse',
        ]);

        return FacilityBooking::create([
            'society_id'  => $society->id,
            'facility_id' => $facility->id,
            'user_id'     => $user->id,
            'flat_id'     => $flat->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time'  => '10:00',
            'end_time'    => '12:00',
            'status'      => 'pending',
        ]);
    }

    public function test_approving_a_booking_queues_an_email_to_the_resident_who_booked_it(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $this->grantManageFacilities($admin);
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $booking = $this->makeBooking($society, $resident, $flat);

        $this->actingAs($admin)
            ->put(route('facility-bookings.update-status', $booking), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('facility_bookings', ['id' => $booking->id, 'status' => 'approved']);

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) use ($resident) {
            return $job->triggerEvent === 'facility_booking_status_updated'
                && $job->recipient['email'] === $resident->email
                && $job->variables['booking_status'] === 'Approved';
        });
    }

    public function test_rejecting_a_booking_queues_a_distinct_notification_from_approving_it(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $this->grantManageFacilities($admin);
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $booking = $this->makeBooking($society, $resident, $flat);

        $this->actingAs($admin)
            ->put(route('facility-bookings.update-status', $booking), ['status' => 'rejected', 'admin_notes' => 'Clubhouse under maintenance.'])
            ->assertRedirect();

        Queue::assertPushed(DispatchEventNotification::class, function (DispatchEventNotification $job) {
            return $job->notificationKey === $job->notificationKey // sanity: job exists
                && str_contains($job->notificationKey, 'rejected');
        });
    }

    public function test_setting_the_same_status_again_does_not_queue_a_second_notification(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $this->grantManageFacilities($admin);
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $booking = $this->makeBooking($society, $resident, $flat);
        $booking->update(['status' => 'approved']);

        $this->actingAs($admin)
            ->put(route('facility-bookings.update-status', $booking), ['status' => 'approved'])
            ->assertRedirect();

        Queue::assertNotPushed(DispatchEventNotification::class);
    }

    public function test_an_admin_cannot_update_another_societys_booking(): void
    {
        Queue::fake();

        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');
        $this->grantManageFacilities($adminB);
        $flatA = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        $bookingA = $this->makeBooking($societyA, $residentA, $flatA);

        $this->actingAs($adminB)
            ->put(route('facility-bookings.update-status', $bookingA), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('facility_bookings', ['id' => $bookingA->id, 'status' => 'pending']);
        Queue::assertNotPushed(DispatchEventNotification::class);
    }
}
