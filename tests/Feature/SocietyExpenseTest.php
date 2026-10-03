<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Society expense management: scope (society vs property) drives what is
 * asked and what is stored; only finance roles reach it; every row is
 * society-scoped; financial changes leave an audit trail.
 */
class SocietyExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function category(Society $society, string $name = 'Security'): ExpenseCategory
    {
        return ExpenseCategory::firstOrCreate(
            ['society_id' => $society->id, 'name' => $name],
            ['status' => 'active']
        );
    }

    private function validPayload(Society $society, array $overrides = []): array
    {
        return array_merge([
            'scope'               => 'society',
            'expense_category_id' => $this->category($society)->id,
            'title'               => 'Security agency - September',
            'amount'              => '120000',
            'expense_date'        => '2025-06-10',
            'vendor_name'         => 'ABC Security Agency',
            'bill_number'         => 'INV-77',
            'payment_method'      => 'online',
            'description'         => 'Monthly invoice',
        ], $overrides);
    }

    public function test_admin_records_a_society_expense_with_no_property_and_society_pays(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        // Even if a stale flat_id / paid_by is submitted with a society
        // expense, the server ignores them - a common expense has no property.
        $response = $this->actingAs($admin)->post(route('expenses.store'), $this->validPayload($society, [
            'flat_id' => $flat->id,
            'paid_by' => 'tenant',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $expense = Expense::first();
        $this->assertNotNull($expense);
        $this->assertSame('society', $expense->scope);
        $this->assertNull($expense->flat_id);
        $this->assertSame('society', $expense->paid_by);
        $this->assertSame('approved', $expense->status);
        $this->assertSame($society->id, $expense->society_id);
        $this->assertSame($admin->id, $expense->created_by);
        $this->assertEquals(120000, $expense->amount);

        $this->assertDatabaseHas('activity_logs', [
            'action'     => 'expense_created',
            'model_type' => Expense::class,
            'model_id'   => $expense->id,
            'society_id' => $society->id,
            'user_id'    => $admin->id,
        ]);
    }

    public function test_a_browser_cannot_turn_a_society_expense_into_a_property_expense(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $flatB    = $this->makeFlat($societyB, $this->makeBuilding($societyB));
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');

        // Tampered scope, another society's flat and a responsibility are
        // all ignored: everything recorded here is a society expense.
        $this->actingAs($adminA)->post(route('expenses.store'), $this->validPayload($societyA, [
            'scope'   => 'property',
            'flat_id' => $flatB->id,
            'paid_by' => 'tenant',
        ]))->assertSessionHasNoErrors();

        $expense = Expense::first();
        $this->assertSame('society', $expense->scope);
        $this->assertNull($expense->flat_id);
        $this->assertSame('society', $expense->paid_by);
    }

    public function test_the_expense_screens_ask_for_no_property_or_scope(): void
    {
        $society = $this->makeSociety();
        $this->makeFlat($society, $this->makeBuilding($society), '101');
        $admin = $this->makeUserWithRole($society, 'Admin');

        $form = $this->actingAs($admin)->get(route('expenses.create'));
        $form->assertOk();
        $form->assertDontSee('name="flat_id"', false);
        $form->assertDontSee('name="scope"', false);
        $form->assertDontSee('name="paid_by"', false);
        $form->assertDontSee('Select property');

        $list = $this->actingAs($admin)->get(route('expenses.index'));
        $list->assertOk();
        $list->assertSee('Total Society Expenses');
        $list->assertDontSee('Property Expenses');
        $list->assertDontSee('not society expense');
        $list->assertDontSee('name="scope"', false);
    }

    public function test_empty_list_says_society_common_expenses(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('No society/common expenses recorded')
            ->assertDontSee('No expenses recorded yet');
    }

    public function test_a_legacy_property_expense_is_not_reachable_from_society_expenses(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $legacy = Expense::create([
            'society_id' => $society->id, 'scope' => 'property', 'flat_id' => $flat->id, 'paid_by' => 'owner',
            'expense_category_id' => $this->category($society, 'Electrical')->id, 'created_by' => $admin->id,
            'title' => 'Flat 101 private electricity', 'amount' => 3500, 'expense_date' => '2025-06-03',
            'payment_method' => 'online', 'status' => 'approved',
        ]);

        $this->actingAs($admin)->get(route('expenses.show', $legacy))->assertNotFound();
        $this->actingAs($admin)->get(route('expenses.edit', $legacy))->assertNotFound();
        // An update must never silently convert it into a society expense.
        $this->actingAs($admin)->put(route('expenses.update', $legacy), $this->validPayload($society))->assertNotFound();
        $this->assertSame('property', $legacy->fresh()->scope);

        $this->actingAs($admin)->get(route('expenses.index'))->assertDontSee('Flat 101 private electricity');
    }

    public function test_category_from_another_society_is_rejected(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');

        $response = $this->actingAs($adminA)->from(route('expenses.create'))
            ->post(route('expenses.store'), $this->validPayload($societyA, [
                'expense_category_id' => $this->category($societyB, 'Gardening')->id,
            ]));

        $response->assertSessionHasErrors('expense_category_id');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_default_categories_are_created_for_the_society_on_first_use(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->assertDatabaseCount('expense_categories', 0);

        $this->actingAs($admin)->get(route('expenses.create'))->assertOk();

        $this->assertSame(
            count(ExpenseCategory::DEFAULTS),
            ExpenseCategory::where('society_id', $society->id)->count()
        );

        // Idempotent.
        $this->actingAs($admin)->get(route('expenses.create'))->assertOk();
        $this->assertDatabaseCount('expense_categories', count(ExpenseCategory::DEFAULTS));
    }

    public function test_residents_guards_and_staff_cannot_reach_expense_management(): void
    {
        $society = $this->makeSociety();
        $payload = $this->validPayload($society);

        foreach (['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Guard', 'Staff'] as $role) {
            $user = $this->makeUserWithRole($society, $role);

            $this->actingAs($user)->get(route('expenses.index'))->assertForbidden();
            $this->actingAs($user)->get(route('expenses.create'))->assertForbidden();
            $this->actingAs($user)->post(route('expenses.store'), $payload)->assertForbidden();
        }

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_accountant_can_record_and_edit_but_not_delete(): void
    {
        $society    = $this->makeSociety();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $this->actingAs($accountant)->get(route('expenses.index'))->assertOk();
        $this->actingAs($accountant)->post(route('expenses.store'), $this->validPayload($society))
            ->assertSessionHasNoErrors();

        $expense = Expense::first();

        $this->actingAs($accountant)->put(route('expenses.update', $expense), $this->validPayload($society, [
            'amount' => '125000',
        ]))->assertSessionHasNoErrors();
        $this->assertEquals(125000, $expense->fresh()->amount);

        $this->actingAs($accountant)->delete(route('expenses.destroy', $expense))->assertForbidden();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_cross_society_expense_access_is_denied(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $adminB   = $this->makeUserWithRole($societyB, 'Admin');

        $this->actingAs($adminA)->post(route('expenses.store'), $this->validPayload($societyA));
        $expense = Expense::first();

        $this->actingAs($adminB)->get(route('expenses.show', $expense))->assertForbidden();
        $this->actingAs($adminB)->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs($adminB)->put(route('expenses.update', $expense), $this->validPayload($societyB, [
            'amount' => '1',
        ]))->assertForbidden();
        $this->actingAs($adminB)->delete(route('expenses.destroy', $expense))->assertForbidden();

        $this->assertEquals(120000, $expense->fresh()->amount);

        // And B's list never shows A's row.
        $this->actingAs($adminB)->get(route('expenses.index'))->assertDontSee('Security agency - September');
        $this->actingAs($adminA)->get(route('expenses.index'))->assertSee('Security agency - September');
    }

    public function test_update_and_delete_leave_an_audit_trail(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post(route('expenses.store'), $this->validPayload($society));
        $expense = Expense::first();

        $this->actingAs($admin)->put(route('expenses.update', $expense), $this->validPayload($society, [
            'amount' => '130000',
        ]))->assertSessionHasNoErrors();

        $updated = ActivityLog::where('action', 'expense_updated')->where('model_id', $expense->id)->first();
        $this->assertNotNull($updated);
        $this->assertSame('120000.00', $updated->old_values['amount']);
        $this->assertSame('130000.00', $updated->new_values['amount']);

        $this->actingAs($admin)->delete(route('expenses.destroy', $expense))->assertRedirect(route('expenses.index'));
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action'   => 'expense_deleted',
            'model_id' => $expense->id,
        ]);
    }

    public function test_index_totals_count_only_society_expenses(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post(route('expenses.store'), $this->validPayload($society, ['amount' => '18000']));
        $this->actingAs($admin)->post(route('expenses.store'), $this->validPayload($society, ['amount' => '20000']));
        // Legacy property row: must not inflate the society total or count.
        Expense::create([
            'society_id' => $society->id, 'scope' => 'property', 'flat_id' => $flat->id, 'paid_by' => 'owner',
            'expense_category_id' => $this->category($society)->id, 'created_by' => $admin->id,
            'title' => 'Flat 101 electricity', 'amount' => 3500, 'expense_date' => '2025-06-03',
            'payment_method' => 'online', 'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('expenses.index'));
        $response->assertOk();
        $this->assertEquals(38000, $response->viewData('societyTotal'));
        $this->assertSame(2, $response->viewData('societyCount'));
        $response->assertDontSee('Flat 101 electricity');
    }

    public function test_index_filters_by_category_vendor_and_dates(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->post(route('expenses.store'), $this->validPayload($society, [
            'title' => 'June security', 'expense_date' => '2025-06-05',
        ]));
        $this->actingAs($admin)->post(route('expenses.store'), $this->validPayload($society, [
            'title' => 'July gardening', 'expense_date' => '2025-07-05',
            'expense_category_id' => $this->category($society, 'Gardening')->id, 'vendor_name' => 'Green Thumb',
        ]));

        $byCategory = $this->actingAs($admin)->get(route('expenses.index', [
            'category_id' => $this->category($society, 'Gardening')->id,
        ]));
        $byCategory->assertSee('July gardening')->assertDontSee('June security');

        $byVendor = $this->actingAs($admin)->get(route('expenses.index', ['vendor' => 'Green']));
        $byVendor->assertSee('July gardening')->assertDontSee('June security');

        $byDate = $this->actingAs($admin)->get(route('expenses.index', ['from' => '2025-06-01', 'to' => '2025-06-30']));
        $byDate->assertSee('June security')->assertDontSee('July gardening');
    }

    public function test_super_admin_without_a_society_is_told_to_switch_first(): void
    {
        $society    = $this->makeSociety();
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)->get(route('expenses.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('expenses.create'))->assertForbidden();
        $this->actingAs($superAdmin)->post(route('expenses.store'), $this->validPayload($society))->assertForbidden();
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_category_management_is_society_scoped(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $catB     = $this->category($societyB, 'Lift');

        $this->actingAs($adminA)->post(route('expenses.categories.store'), ['name' => 'Water Tanker'])
            ->assertRedirect(route('expenses.categories'));
        $this->assertDatabaseHas('expense_categories', ['society_id' => $societyA->id, 'name' => 'Water Tanker']);

        $this->actingAs($adminA)->patch(route('expenses.categories.toggle', $catB))->assertForbidden();
        $this->assertSame('active', $catB->fresh()->status);
    }
}
