<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Festival & Celebration module. Additive only: new tables, nothing existing is altered.
 *
 * Money in a festival is a separate fund. Voluntary contributions are NOT maintenance income and festival spending is NOT a
 * society expense, so none of these tables feeds the society Financial Report (see App\Services\FestivalService).
 * Statuses are plain strings validated in the application (no CHECK constraints to rebuild later on SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festivals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('society_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->date('festival_date');
            $t->date('end_date')->nullable();
            $t->string('venue')->nullable();
            $t->unsignedInteger('expected_attendance')->nullable();
            $t->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('draft');            // draft | published | closed | cancelled
            $t->decimal('estimated_budget', 12, 2)->default(0);
            $t->decimal('approved_budget', 12, 2)->nullable();
            $t->decimal('contribution_target', 12, 2)->nullable();   // a voluntary goal, never a due
            $t->decimal('approval_limit', 12, 2)->nullable();        // expenses above this need an Admin (null = no limit)
            $t->decimal('opening_balance', 12, 2)->default(0);       // only a verified carried-forward amount
            $t->string('opening_balance_note')->nullable();
            $t->boolean('separate_approver')->default(true);         // the person who entered an expense cannot approve it
            $t->boolean('allow_anonymous')->default(false);
            $t->text('arrangements')->nullable();
            $t->string('series_key', 80)->nullable();                // groups the yearly editions of one festival; records are never shared
            $t->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $t->timestamp('announced_at')->nullable();
            $t->string('settlement_status', 20)->default('open');    // open | settled
            $t->timestamp('settled_at')->nullable();
            $t->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('settlement_decision')->nullable();            // surplus / shortfall decision, as recorded by the society
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['society_id', 'festival_date']);
        });

        Schema::create('festival_budget_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('society_id')->constrained()->cascadeOnDelete();
            $t->foreignId('festival_id')->constrained()->cascadeOnDelete();
            $t->string('category', 80);
            $t->string('description')->nullable();
            $t->decimal('estimated_amount', 12, 2)->default(0);
            $t->string('vendor')->nullable();
            $t->decimal('quotation_amount', 12, 2)->nullable();
            $t->decimal('approved_limit', 12, 2)->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['festival_id']);
        });

        Schema::create('festival_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('society_id')->constrained()->cascadeOnDelete();
            $t->foreignId('festival_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default('volunteer');            // committee | volunteer
            $t->string('responsibility')->nullable();
            $t->timestamps();
            $t->unique(['festival_id', 'user_id']);
        });

        Schema::create('festival_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('society_id')->constrained()->cascadeOnDelete();
            $t->foreignId('festival_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('body')->nullable();
            $t->dateTime('scheduled_for')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('festival_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('society_id')->constrained()->cascadeOnDelete();
            $t->foreignId('festival_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('flat_id')->nullable()->constrained('flats')->nullOnDelete();
            $t->string('contributor_name')->nullable();
            $t->boolean('is_anonymous')->default(false);
            $t->decimal('amount', 12, 2);
            // pledged = a resident's intention, NOT money; received = verified receipt; cancelled / refunded keep the history
            $t->string('status', 20)->default('pledged');
            $t->string('payment_method', 20)->nullable();
            $t->string('payment_reference', 100)->nullable();
            $t->date('received_on')->nullable();
            $t->string('receipt_number', 40)->nullable();
            $t->decimal('refunded_amount', 12, 2)->default(0);
            $t->timestamp('refunded_at')->nullable();
            $t->string('refund_reason')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->string('request_token', 64)->nullable();            // idempotency for a double submit
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['society_id', 'payment_reference'], 'festival_contrib_ref_unique');
            $t->unique(['society_id', 'receipt_number'], 'festival_contrib_receipt_unique');
            $t->unique(['festival_id', 'request_token'], 'festival_contrib_token_unique');
            $t->index(['festival_id', 'status']);
        });

        Schema::create('festival_expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('society_id')->constrained()->cascadeOnDelete();
            $t->foreignId('festival_id')->constrained()->cascadeOnDelete();
            $t->foreignId('budget_item_id')->nullable()->constrained('festival_budget_items')->nullOnDelete();
            $t->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $t->string('vendor');
            $t->string('purpose');
            $t->decimal('amount', 12, 2);
            $t->date('expense_date');
            $t->string('payment_method', 20)->nullable();
            $t->string('payment_reference', 100)->nullable();
            $t->string('invoice_path')->nullable();
            // draft -> submitted -> approved | rejected -> paid ; cancelled from draft/submitted/approved
            $t->string('status', 20)->default('draft');
            $t->string('rejection_reason')->nullable();
            $t->string('request_token', 64)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('paid_on')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['festival_id', 'request_token'], 'festival_expense_token_unique');
            $t->index(['festival_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['festival_expenses', 'festival_contributions', 'festival_updates', 'festival_members', 'festival_budget_items', 'festivals'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
