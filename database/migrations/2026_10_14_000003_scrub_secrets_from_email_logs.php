<?php

use App\Models\EmailLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery log used to keep every template variable of every email it sent - including the temporary password of
 * each new account and reset links - and the log's detail screen printed them. New rows no longer carry those values
 * (EmailLog::scrubbed); this removes them from rows written before: the value is replaced by "[hidden]". Nothing else in
 * a row changes and no row is deleted. A secret that has been shown cannot be un-shown, so this cannot be undone; the
 * accounts concerned should be asked to change their password if their first one was never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_logs') || !Schema::hasColumn('email_logs', 'variables')) {
            return;
        }

        DB::table('email_logs')->whereNotNull('variables')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $vars = json_decode((string) $row->variables, true);
                if (!is_array($vars)) {
                    continue;
                }
                $clean = EmailLog::scrubbed($vars);
                if ($clean !== $vars) {
                    DB::table('email_logs')->where('id', $row->id)->update(['variables' => json_encode($clean)]);
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible by design: the removed values are not kept anywhere.
    }
};
