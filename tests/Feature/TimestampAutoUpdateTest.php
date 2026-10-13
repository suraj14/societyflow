<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A column the application sets once must stay as set. On MariaDB / MySQL (explicit_defaults_for_timestamp off) a
 * NOT NULL timestamp without a default silently gets "ON UPDATE CURRENT_TIMESTAMP" - `expires_at` of a one-time code was
 * rewritten by every wrong guess, so an expired code stayed usable. SQLite never showed it; the MariaDB run did.
 */
class TimestampAutoUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_application_timestamp_column_rewrites_itself_on_update(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Only MySQL / MariaDB add an implicit ON UPDATE to a timestamp column.');
        }

        $offenders = DB::select(
            "select table_name t, column_name c from information_schema.columns where table_schema = database() and data_type = 'timestamp' and lower(extra) like '%on update%'"
        );

        $this->assertSame([], array_map(fn ($r) => $r->t . '.' . $r->c, $offenders));
    }

    public function test_a_wrong_guess_does_not_move_the_expiry_and_an_old_code_stays_expired_on_every_engine(): void
    {
        [$row, $code] = MobileOtp::issue('9811113333', 'login');
        $expires = DB::table('mobile_otps')->where('id', $row->id)->value('expires_at');

        $this->assertFalse(MobileOtp::check('9811113333', 'login', $code === '1111' ? '2222' : '1111'));   // an UPDATE of the row (attempts + 1)

        $this->assertSame($expires, DB::table('mobile_otps')->where('id', $row->id)->value('expires_at'), 'the expiry is exactly what was issued');

        $this->travel(MobileOtp::TTL_MINUTES + 1)->minutes();
        $this->assertFalse(MobileOtp::check('9811113333', 'login', $code), 'six minutes later the code is dead, however many guesses came before');
    }
}
