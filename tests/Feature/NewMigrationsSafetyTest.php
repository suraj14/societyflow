<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\MobileOtp;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The 2026-10-13/14 migrations, run the way an operator would meet them: up on existing data, down, up again - and what
 * they must NOT do (delete a row, lose a bill, resurrect a secret). Each runs on whichever engine the suite is on
 * (SQLite in memory, or MariaDB/MySQL in the pre-release run).
 *
 * On MySQL/MariaDB a migration's DDL implicitly commits, so the rows these tests create survive the test's own rollback;
 * each test deletes exactly what it made.
 */
class NewMigrationsSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $name): object
    {
        return require database_path("migrations/{$name}.php");
    }

    public function test_whatsapp_manual_migration_rolls_back_and_reapplies_keeping_every_template(): void
    {
        $society = $this->makeSociety();
        $t = WhatsAppTemplate::create(['society_id' => $society->id, 'external_id' => 'manual-x', 'name' => 'visitor_v1', 'purpose' => 'visitor', 'is_active' => true, 'source' => 'manual',
            'status' => 'LOCAL', 'var_count' => 1, 'variable_map' => ['resident_name'], 'body_text' => 'Hi {{1}}']);
        $m = $this->migration('2026_10_14_000001_make_whatsapp_templates_manual');

        try {
            $m->down();
            foreach (['purpose', 'is_active', 'source', 'variable_map'] as $column) {
                $this->assertFalse(Schema::hasColumn('whatsapp_templates', $column), "$column removed");
            }
            $this->assertSame(1, DB::table('whatsapp_templates')->count(), 'no template lost');
            $this->assertSame('Hi {{1}}', DB::table('whatsapp_templates')->value('body_text'));

            $m->up();
            foreach (['purpose', 'is_active', 'source', 'variable_map'] as $column) {
                $this->assertTrue(Schema::hasColumn('whatsapp_templates', $column), "$column back");
            }
            $this->assertSame(1, DB::table('whatsapp_templates')->count());
        } finally {
            DB::table('whatsapp_templates')->where('external_id', 'manual-x')->delete();
            \App\Models\Society::whereKey($society->id)->delete();
        }
    }

    public function test_one_time_code_migration_retires_waiting_codes_and_reverses_cleanly(): void
    {
        [$row] = MobileOtp::issue('9811113333', 'login');
        $m = $this->migration('2026_10_14_000002_harden_mobile_otps');

        try {
            $m->down();
            $this->assertFalse(Schema::hasColumn('mobile_otps', 'purpose'));
            $this->assertSame(1, DB::table('mobile_otps')->count(), 'the row is kept');
            $this->assertSame(0, DB::table('mobile_otps')->where('is_used', false)->count(), 'a waiting code is retired, never left usable');
            $this->assertSame('0000', DB::table('mobile_otps')->value('otp'), 'a hash cannot be turned back into digits, so it is replaced');

            $m->up();
            $this->assertTrue(Schema::hasColumn('mobile_otps', 'purpose'));
            [$fresh, $code] = MobileOtp::issue('9811113333', 'login');
            $this->assertTrue(MobileOtp::check('9811113333', 'login', $code), 'the hardened table works after a round trip');
        } finally {
            DB::table('mobile_otps')->where('phone', '9811113333')->delete();
        }
    }

    public function test_the_bill_index_migration_keeps_every_bill_and_refuses_a_rollback_that_would_lose_one(): void
    {
        $society = $this->makeSociety();
        $flat = $this->makeFlat($society, $this->makeBuilding($society));
        $bill = fn (string $type) => MaintenanceBill::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'month' => 'October', 'year' => 2026, 'bill_type' => $type,
            'bill_date' => '2026-10-01', 'due_date' => '2026-10-15', 'maintenance_amount' => 1000, 'paid_amount' => 0]);
        $maintenance = $bill('maintenance');
        $m = $this->migration('2026_10_13_000002_make_bill_uniqueness_per_type');

        try {
            // a clean state rolls back and forward again without touching the bill
            $m->down();
            $this->assertSame(1, MaintenanceBill::count());
            $m->up();
            $this->assertSame(1, MaintenanceBill::count());

            // a rent bill beside the maintenance bill is allowed now - and then the old one-per-month rule cannot come back
            $rent = $bill('rent');
            $this->assertSame(2, MaintenanceBill::count());
            $refused = false;
            try {
                $m->down();
            } catch (\RuntimeException $e) {
                $refused = true;
                $this->assertStringContainsString('Cannot roll back', $e->getMessage());
            }
            $this->assertTrue($refused, 'rolling back would have meant deleting a financial record');
            $this->assertSame(2, MaintenanceBill::count(), 'both bills are still there');
        } finally {
            MaintenanceBill::where('society_id', $society->id)->delete();
            \App\Models\Flat::where('society_id', $society->id)->delete();
            DB::table('buildings')->where('society_id', $society->id)->delete();
            \App\Models\Society::whereKey($society->id)->delete();
        }
    }

    public function test_the_email_log_scrub_hides_old_secrets_keeps_everything_else_and_deletes_nothing(): void
    {
        $society = $this->makeSociety();
        $id = DB::table('email_logs')->insertGetId([
            'society_id' => $society->id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'user_registered', 'channel' => 'email', 'status' => 'sent',
            'variables' => json_encode(['user_name' => 'Asha', 'temporary_password' => 'Xk9-secret-pass', 'reset_url' => 'https://x/reset/tok', 'login_url' => 'https://x/login']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $untouched = DB::table('email_logs')->insertGetId([
            'society_id' => $society->id, 'recipient_email' => 'b@example.test', 'trigger_action' => 'notice_published', 'channel' => 'email', 'status' => 'sent',
            'variables' => json_encode(['notice_title' => 'Water']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = DB::table('email_logs')->count();

        try {
            $this->migration('2026_10_14_000003_scrub_secrets_from_email_logs')->up();

            $vars = json_decode(DB::table('email_logs')->where('id', $id)->value('variables'), true);
            $this->assertSame('[hidden]', $vars['temporary_password']);
            $this->assertSame('[hidden]', $vars['reset_url']);
            $this->assertSame('Asha', $vars['user_name']);
            $this->assertSame('https://x/login', $vars['login_url']);
            $this->assertSame(['notice_title' => 'Water'], json_decode(DB::table('email_logs')->where('id', $untouched)->value('variables'), true));
            $this->assertSame($before, DB::table('email_logs')->count(), 'no row deleted');

            $this->migration('2026_10_14_000003_scrub_secrets_from_email_logs')->down();   // a no-op, never an error
            $this->assertSame('[hidden]', json_decode(DB::table('email_logs')->where('id', $id)->value('variables'), true)['temporary_password'], 'a hidden secret does not come back');
        } finally {
            DB::table('email_logs')->whereIn('id', [$id, $untouched])->delete();
            \App\Models\Society::whereKey($society->id)->delete();
        }
    }
}
