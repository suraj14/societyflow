<?php

namespace Tests\Feature;

use App\Enums\EmailEvent;
use App\Models\EmailLog;
use App\Services\DynamicEmailService;
use App\Support\NotificationEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * DynamicEmailService::sendByEvent() refuses any trigger that is not an
 * EmailEvent case ("Invalid email event", returns false, sends nothing).
 * access_pass_created, access_pass_revoked and
 * facility_booking_status_updated (P1-61) were registered, templated and
 * dispatched - and every test passed, because those tests faked the queue
 * and only asserted the job was dispatched. In a real run the job reached
 * sendByEvent() and silently stopped. Found while browser-testing the
 * Society Rules announcement, which had the same gap.
 *
 * This runs the real send path for every registered trigger_event email.
 */
class EmailEventWhitelistTest extends TestCase
{
    use RefreshDatabase;

    public static function triggerEventEmails(): array
    {
        $cases = [];
        foreach (NotificationEvents::all() as $key => $event) {
            if ($event['lookup'] === NotificationEvents::LOOKUP_TRIGGER_EVENT && in_array('email', $event['channels'], true)) {
                $cases[$key] = [$event['templates'][0]];
            }
        }

        return $cases;
    }

    /** @dataProvider triggerEventEmails */
    public function test_registered_trigger_event_is_an_accepted_email_event(string $trigger): void
    {
        $this->assertContains($trigger, EmailEvent::values(), "'{$trigger}' is registered but DynamicEmailService would reject it.");
    }

    /** @dataProvider triggerEventEmails */
    public function test_registered_trigger_event_email_is_actually_sent(string $trigger): void
    {
        Mail::fake();
        $society = $this->makeSociety();

        $sent = DynamicEmailService::sendByEvent(
            $trigger,
            [['email' => 'resident@test.local', 'name' => 'Resident', 'role' => 'Tenant']],
            ['user_name' => 'Resident'],
            $society->id,
            "whitelist-test:{$trigger}",
        );

        $this->assertTrue($sent, "sendByEvent('{$trigger}') sent nothing.");
        $this->assertTrue(
            EmailLog::where('notification_key', "whitelist-test:{$trigger}")->where('status', 'sent')->exists(),
            "No 'sent' EmailLog row for '{$trigger}'."
        );
    }
}
