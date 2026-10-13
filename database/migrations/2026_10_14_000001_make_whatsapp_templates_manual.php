<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp templates are entered by hand now (copied from the provider portal), not synced. The existing
 * `whatsapp_templates` table is reused rather than adding a second one:
 *
 *   purpose       which notification the template is for (App\Support\WhatsAppPurposes key)
 *   is_active     the one template a notification uses; at most one active per purpose and society
 *   source        'manual' (typed in) or 'synced' (every row that exists before this migration)
 *   variable_map  JSON list: which value fills {{1}}, {{2}} ... (was the `whatsapp_{purpose}_variables` setting)
 *
 * Existing choices are preserved: where a society had attached a template to a notification
 * (`whatsapp_{purpose}_template_name` / `_variables` settings), that row becomes the active template of the purpose.
 * Nothing is deleted or rewritten, and the old settings rows are left in place as the fallback the sender still reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_templates')) {
            return;
        }

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('purpose', 40)->nullable()->after('society_id');
            $table->boolean('is_active')->default(false)->after('status');
            $table->string('source', 10)->default('synced')->after('is_active');
            $table->text('variable_map')->nullable()->after('var_count');
            $table->index(['society_id', 'purpose', 'is_active'], 'whatsapp_templates_purpose_active_index');
        });

        $this->carryOverAssignments();
    }

    private function carryOverAssignments(): void
    {
        $names = DB::table('system_settings')->where('group', 'whatsapp')->whereNotNull('value')->where('value', '!=', '')->get()
            ->filter(fn ($row) => preg_match('/^whatsapp_.+_template_name$/', $row->key) === 1);

        foreach ($names as $row) {
            if (!preg_match('/^whatsapp_(.+)_template_name$/', $row->key, $m) || $m[1] === 'otp') {
                continue;
            }
            $purpose = $m[1];
            $template = DB::table('whatsapp_templates')
                ->where('name', $row->value)
                ->where(fn ($q) => $row->society_id === null ? $q->whereNull('society_id') : $q->where('society_id', $row->society_id))
                ->orderByDesc('id')->first();
            if (!$template) {
                continue;
            }

            $vars = DB::table('system_settings')->where('group', 'whatsapp')->where('key', "whatsapp_{$purpose}_variables")
                ->where(fn ($q) => $row->society_id === null ? $q->whereNull('society_id') : $q->where('society_id', $row->society_id))->value('value');
            $decoded = is_string($vars) ? json_decode($vars, true) : null;

            DB::table('whatsapp_templates')->where('id', $template->id)->update([
                'purpose'      => $purpose,
                'is_active'    => true,
                'variable_map' => is_array($decoded) ? json_encode(array_values($decoded)) : null,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_templates')) {
            return;
        }

        // One change per statement: SQLite cannot drop several columns in one go, and MySQL needs the index gone first.
        Schema::table('whatsapp_templates', fn (Blueprint $t) => $t->dropIndex('whatsapp_templates_purpose_active_index'));
        foreach (['variable_map', 'source', 'is_active', 'purpose'] as $column) {
            Schema::table('whatsapp_templates', fn (Blueprint $t) => $t->dropColumn($column));
        }
    }
};
