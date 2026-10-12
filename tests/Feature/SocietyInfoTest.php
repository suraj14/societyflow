<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\User;
use App\Support\SocietyInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Society Info: the former "Rules & Documents" entry, now three sections on
 * the one existing page - Society Details, Committee, Documents - on web and
 * in the app, built from one shared App\Support\SocietyInfo.
 *
 * Informational only. Nothing here is invented: an unrecorded value is
 * "Not available", and a committee member exists only when a real user of the
 * society has been given a designation.
 */
class SocietyInfoTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Green Meadows');
    }

    private function member(string $name, ?string $designation, ?string $phone = '9876543210', array $over = [], ?Society $society = null): User
    {
        return $this->makeUserWithRole($society ?? $this->society, 'Tenant', array_merge([
            'name' => $name,
            'phone' => $phone,
            'committee_designation' => $designation,
        ], $over));
    }

    private function names(string $html): array
    {
        preg_match_all('#data-committee-member.*?<p class="font-semibold text-gray-900 truncate">(.*?)</p>#s', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    // =============================================================== schema

    public function test_the_three_informational_fields_exist_and_are_empty_by_default(): void
    {
        $this->assertTrue(Schema::hasColumn('societies', 'registration_number'));
        $this->assertTrue(Schema::hasColumn('societies', 'registered_on'));
        $this->assertTrue(Schema::hasColumn('users', 'committee_designation'));

        $fresh = $this->makeSociety('Fresh');
        $this->assertNull($fresh->fresh()->registration_number);
        $this->assertNull($fresh->fresh()->registered_on);
        $this->assertNull($this->makeUserWithRole($fresh, 'Tenant')->fresh()->committee_designation);
    }

    // ================================================= navigation and label

    public function test_one_society_info_menu_entry_replaces_the_old_labels_without_a_new_top_level_menu(): void
    {
        foreach (['Admin', 'Tenant', 'Owner'] as $role) {
            $user = $this->makeUserWithRole($this->society, $role);
            $html = $this->actingAs($user)->get(route('society-rules.index'))->assertOk()->getContent();

            // exactly one sidebar entry (grouped sidebar: <span class="truncate">) and one mobile-menu entry
            // (<span class="ml-3">) carry the label
            $this->assertSame(1, substr_count($html, '<span class="truncate">Society Info</span>'), "$role: one entry in the sidebar");
            $this->assertSame(1, substr_count($html, '<span class="ml-3">Society Info</span>'), "$role: one entry in the mobile menu");
            foreach (['Rules &amp; Documents', 'Society Rules'] as $old) {
                $this->assertStringNotContainsString(">$old</span>", $html, "$role: old label $old");
            }
        }
    }

    public function test_the_page_has_exactly_the_three_sections_in_order(): void
    {
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $html = $this->actingAs($tenant)->get(route('society-rules.index'))->assertOk()->getContent();

        preg_match_all('#role="tab" id="tab-(\w+)"#', $html, $tabs);
        $this->assertSame(['details', 'committee', 'documents'], $tabs[1]);
        $this->assertSame(1, preg_match('#<span>Society Details</span>#', $html));
        $this->assertSame(1, preg_match('#<span>Committee</span>#', $html));
        $this->assertSame(1, preg_match('#<span>Documents</span>#', $html));
        $this->assertSame(3, substr_count($html, 'role="tabpanel"'));
    }

    public function test_the_tab_can_be_deep_linked_and_a_document_search_stays_on_documents(): void
    {
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $this->actingAs($tenant)->get(route('society-rules.index'))->assertSee("x-data=\"{ tab: 'details' }\"", false);
        $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => 'committee']))->assertSee("x-data=\"{ tab: 'committee' }\"", false);
        $this->actingAs($tenant)->get(route('society-rules.index', ['q' => 'parking']))->assertSee("x-data=\"{ tab: 'documents' }\"", false);
        // an unknown tab never reaches the markup
        $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => "x'}); alert(1);//"]))
            ->assertSee("x-data=\"{ tab: 'details' }\"", false)->assertDontSee('alert(1)', false);
    }

    // ========================================================= society details

    public function test_details_show_real_values_and_say_not_available_for_the_rest(): void
    {
        $this->society->update(['address' => '12 Lake Road', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']);
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        // Nothing recorded yet for registration number / date.
        $html = $this->actingAs($tenant)->get(route('society-rules.index'))->assertOk()
            ->assertSee('Green Meadows')
            ->assertSee('12 Lake Road, Pune, Maharashtra, 411001')
            ->assertSee('Mixed (Apartments + Villas)')
            ->getContent();
        $this->assertSame(2, substr_count($html, 'Not available'), 'registration number and date are unrecorded, nothing else');

        $this->society->update(['registration_number' => 'MH/PUN/HSG/2015/0042', 'registered_on' => '2015-04-17']);

        // A real request loads the user afresh; this test object has already
        // cached its `society` relation from the request above.
        $html = $this->actingAs($tenant->fresh())->get(route('society-rules.index'))->assertOk()
            ->assertSee('MH/PUN/HSG/2015/0042')
            ->assertSee('17 Apr 2015')
            ->getContent();
        $this->assertSame(0, substr_count($html, 'Not available'));
    }

    public function test_registration_placeholders_and_a_fresh_registration_form_address_are_not_shown_as_facts(): void
    {
        $this->society->update(['address' => 'To be updated', 'city' => 'To be updated', 'state' => 'To be updated', 'pincode' => '000000', 'registration_number' => '  ']);

        $details = SocietyInfo::details($this->society->fresh());

        $this->assertNull($details['address']);
        $this->assertNull($details['registration_number']);
        $this->assertNull($details['registered_on']);
        $this->assertSame('Mixed (Apartments + Villas)', $details['society_type']);
    }

    public function test_the_society_type_follows_the_existing_property_type_setting(): void
    {
        $this->society->update(['property_type' => 'villa']);
        $this->assertSame('Villa Township', SocietyInfo::details($this->society->fresh())['society_type']);

        $this->society->update(['property_type' => 'apartment']);
        $this->assertSame('Apartment Society', SocietyInfo::details($this->society->fresh())['society_type']);
    }

    // ================================================================ committee

    public function test_with_no_designations_the_committee_is_an_honest_empty_state(): void
    {
        $this->member('Plain Resident', null);
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $html = $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => 'committee']))->assertOk()
            ->assertSee('No committee members listed yet')
            ->getContent();

        $this->assertSame([], $this->names($html));
        $this->assertStringNotContainsString('tel:', $html, 'no invented people or numbers');
        $this->assertSame([], SocietyInfo::committee($this->society));
    }

    public function test_committee_members_are_listed_in_office_order_with_a_call_button_only_for_real_numbers(): void
    {
        $this->member('Zoya Treasurer', 'Treasurer', '9000000003');
        $this->member('Raj Secretary', 'Secretary', '9000000002');
        $this->member('Asha President', 'President', '+91 98765 43210');
        $this->member('Meera Member', 'Committee Member', '9000000004');
        $this->member('Bina Nophone', 'Joint Secretary', null);
        $this->member('Cara Placeholder', 'Vice President', '0000000000');
        $this->member('Dev Short', 'Joint Treasurer', '12345');
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $html = $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => 'committee']))->assertOk()->getContent();

        $this->assertSame(
            ['Asha President', 'Cara Placeholder', 'Raj Secretary', 'Bina Nophone', 'Zoya Treasurer', 'Dev Short', 'Meera Member'],
            $this->names($html),
            'President, Vice President, Secretary, Joint Secretary, Treasurer, Joint Treasurer, then the rest'
        );

        // Call buttons: the three valid numbers only (+ the other valid members), never for blank/placeholder/short.
        $this->assertStringContainsString('href="tel:+919876543210"', $html);
        $this->assertStringContainsString('href="tel:9000000002"', $html);
        $this->assertStringContainsString('aria-label="Call Asha President, President"', $html);
        $this->assertStringNotContainsString('tel:0000000000', $html);
        $this->assertStringNotContainsString('tel:12345', $html);
        $this->assertSame(4, substr_count($html, 'href="tel:'), 'Asha, Raj, Zoya, Meera only');
    }

    public function test_the_committee_shows_each_persons_profile_photo_or_initials(): void
    {
        $this->member('Asha President', 'President', '9000000001', ['avatar' => 'avatars/asha.png']);
        $this->member('Raj Secretary', 'Secretary', '9000000002');
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $html = $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => 'committee']))->assertOk()->getContent();

        $this->assertStringContainsString('storage/avatars/asha.png', $html);
        $this->assertStringContainsString('alt="Photo of Asha President"', $html);
        $this->assertStringContainsString('>RS</div>', $html, 'a member without a photo gets initials, not a stock image');
    }

    public function test_only_active_members_of_this_society_with_a_designation_appear(): void
    {
        $this->member('Asha President', 'President');
        $this->member('Gone Secretary', 'Secretary', '9000000009', ['status' => 'inactive']);
        $this->member('Blank Designation', '   ');
        $other = $this->makeSociety('Other Society');
        $this->member('Other President', 'President', '9111111111', [], $other);
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $html = $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => 'committee']))->assertOk()->getContent();

        $this->assertSame(['Asha President'], $this->names($html));
        $this->assertStringNotContainsString('Other President', $html);
        $this->assertStringNotContainsString('9111111111', $html);
    }

    // =================================================== guards and permissions

    public function test_guards_get_the_same_three_sections_with_only_guard_visible_documents(): void
    {
        $this->member('Asha President', 'President', '9000000001');
        $guard = $this->makeUserWithRole($this->society, 'Guard');

        $html = $this->actingAs($guard)->get(route('guard.rules.index'))->assertOk()
            ->assertSee('Society Info')->assertSee('Green Meadows')->getContent();

        $this->assertSame(3, substr_count($html, 'role="tabpanel"'));
        $this->assertStringContainsString('Asha President', $html);
        $this->assertStringContainsString('aria-label="Society Info"', $html, 'the gate header button carries the new label');
    }

    public function test_staff_and_accountants_still_cannot_open_society_info(): void
    {
        foreach (['Staff', 'Accountant'] as $role) {
            $user = $this->makeUserWithRole($this->society, $role);
            $this->actingAs($user)->get(route('society-rules.index'))->assertForbidden();
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/society/info')->assertForbidden();
        }
    }

    // ============================================================ admin editing

    public function test_an_admin_records_the_registration_details_and_can_clear_them(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $form = [
            'name' => 'Green Meadows', 'address' => '12 Lake Road', 'city' => 'Pune', 'state' => 'MH', 'country' => 'India', 'pincode' => '411001',
        ];

        $this->actingAs($admin)->put(route('societies.update', $this->society), $form + [
            'registration_number' => '  MH/PUN/2015/42 ', 'registered_on' => '2015-04-17',
        ])->assertSessionDoesntHaveErrors();
        $this->society->refresh();
        $this->assertSame('MH/PUN/2015/42', $this->society->registration_number);
        $this->assertSame('2015-04-17', $this->society->registered_on->toDateString());

        $this->actingAs($admin)->put(route('societies.update', $this->society), $form + ['registration_number' => '', 'registered_on' => ''])
            ->assertSessionDoesntHaveErrors();
        $this->assertNull($this->society->fresh()->registration_number);
        $this->assertNull($this->society->fresh()->registered_on);

        $this->actingAs($admin)->put(route('societies.update', $this->society), $form + ['registered_on' => now()->addYear()->toDateString()])
            ->assertSessionHasErrors('registered_on');
    }

    public function test_an_admin_cannot_edit_another_societys_registration_details(): void
    {
        $other = $this->makeSociety('Other');
        $adminA = $this->makeUserWithRole($this->society, 'Admin');

        $response = $this->actingAs($adminA)->put(route('societies.update', $other), [
            'name' => 'Hijacked', 'address' => 'x', 'city' => 'x', 'state' => 'x', 'country' => 'x', 'pincode' => '1',
            'registration_number' => 'EVIL/1',
        ]);

        $this->assertContains($response->getStatusCode(), [302, 403, 404]);
        $this->assertNull($other->fresh()->registration_number);
        $this->assertSame('Other', $other->fresh()->name);
    }

    public function test_an_admin_adds_and_removes_committee_members_through_the_user_form(): void
    {
        $admin  = $this->makeUserWithRole($this->society, 'Admin');
        $target = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Asha Rao', 'phone' => '9876543210']);
        $form   = ['name' => 'Asha Rao', 'phone' => '9876543210', 'role' => 'Tenant', 'status' => 'active'];

        $this->actingAs($admin)->get(route('users.edit', $target))->assertOk()
            ->assertSee('name="committee_designation"', false)->assertSee('Secretary');

        $this->actingAs($admin)->put(route('users.update', $target), $form + ['committee_designation' => '  Secretary '])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('Secretary', $target->fresh()->committee_designation);
        $this->assertSame(['Asha Rao'], array_column(SocietyInfo::committee($this->society), 'name'));

        $this->actingAs($admin)->put(route('users.update', $target), $form + ['committee_designation' => ''])
            ->assertSessionDoesntHaveErrors();
        $this->assertNull($target->fresh()->committee_designation);
        $this->assertSame([], SocietyInfo::committee($this->society));

        $this->actingAs($admin)->put(route('users.update', $target), $form + ['committee_designation' => str_repeat('x', 101)])
            ->assertSessionHasErrors('committee_designation');
    }

    public function test_a_resident_cannot_make_themselves_a_committee_member(): void
    {
        $tenant = $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->actingAs($tenant)->put(route('users.update', $tenant), [
            'name' => $tenant->name, 'phone' => '9876543210', 'role' => 'Tenant', 'status' => 'active', 'committee_designation' => 'President',
        ]);
        $this->actingAs($tenant)->put('/profile', ['name' => $tenant->name, 'email' => $tenant->email, 'committee_designation' => 'President']);

        $this->assertNull($tenant->fresh()->committee_designation);
    }

    public function test_admin_sees_the_manage_shortcuts_and_the_documents_screen_shares_the_frame(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $this->actingAs($admin)->get(route('society-rules.index'))->assertOk()
            ->assertSee('Manage documents')->assertSee('Edit society details')->assertSee('Committee designation');
        $this->actingAs($tenant)->get(route('society-rules.index'))->assertOk()
            ->assertDontSee('Manage documents')->assertDontSee('Edit society details');

        $this->actingAs($admin)->get(route('admin.society-documents.index'))->assertOk()
            ->assertSee('Society Info sections')->assertSee('Society Details')->assertSee('Committee');
    }

    // ===================================================================== API

    public function test_the_app_gets_the_same_details_and_committee_as_the_web_page(): void
    {
        $this->society->update(['address' => '12 Lake Road', 'city' => 'Pune', 'state' => 'MH', 'pincode' => '411001', 'registration_number' => 'REG/42', 'registered_on' => '2015-04-17']);
        $this->member('Raj Secretary', 'Secretary', '9000000002');
        $this->member('Asha President', 'President', '+91 98765 43210', ['avatar' => 'avatars/asha.png']);
        $this->member('Bina Nophone', 'Treasurer', null);
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        Sanctum::actingAs($tenant);
        $res = $this->getJson('/api/v1/society/info')->assertOk()->assertJsonPath('success', true);

        $res->assertJsonPath('data.details', [
            'name' => 'Green Meadows',
            'registration_number' => 'REG/42',
            'address' => '12 Lake Road, Pune, MH, 411001',
            'society_type' => 'Mixed (Apartments + Villas)',
            'registered_on' => '2015-04-17',
            'registered_on_label' => '17 Apr 2015',
        ]);

        $committee = $res->json('data.committee');
        $this->assertSame(['Asha President', 'Raj Secretary', 'Bina Nophone'], array_column($committee, 'name'));
        $this->assertSame(['id', 'name', 'designation', 'phone', 'can_call', 'photo'], array_keys($committee[0]), 'no browser-only keys in the app payload');
        $this->assertSame('/storage/avatars/asha.png', $committee[0]['photo']);
        $this->assertSame('+919876543210', $committee[0]['phone']);
        $this->assertTrue($committee[0]['can_call']);
        $this->assertNull($committee[2]['phone']);
        $this->assertFalse($committee[2]['can_call']);
        $this->assertNull($committee[1]['photo']);

        // Web and app read the same builder, so they cannot disagree.
        $web = $this->actingAs($tenant)->get(route('society-rules.index', ['tab' => 'committee']))->getContent();
        $this->assertSame(array_column($committee, 'name'), $this->names($web));
    }

    public function test_an_unrecorded_society_reports_nulls_not_invented_values(): void
    {
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');
        Sanctum::actingAs($tenant);

        $data = $this->getJson('/api/v1/society/info')->assertOk()->json('data');

        $this->assertNull($data['details']['registration_number']);
        $this->assertNull($data['details']['registered_on']);
        $this->assertNull($data['details']['registered_on_label']);
        $this->assertSame([], $data['committee']);
    }

    /** @dataProvider readers */
    public function test_every_reader_role_can_read_their_own_societys_info_and_never_another(string $role): void
    {
        $this->member('Own President', 'President');
        $other = $this->makeSociety('Other Society');
        $other->update(['registration_number' => 'OTHER/1']);
        $this->member('Other President', 'President', '9111111111', [], $other);

        Sanctum::actingAs($this->makeUserWithRole($this->society, $role));
        $res = $this->getJson('/api/v1/society/info')->assertOk();

        $this->assertSame('Green Meadows', $res->json('data.details.name'));
        $this->assertSame(['Own President'], array_column($res->json('data.committee'), 'name'));
        $this->assertStringNotContainsString('OTHER/1', $res->getContent());
        $this->assertStringNotContainsString('9111111111', $res->getContent());
    }

    /** @return array<string, array{string}> */
    public static function readers(): array
    {
        return ['Admin' => ['Admin'], 'Owner' => ['Owner'], 'Villa Owner' => ['Villa Owner'], 'Apartment Owner' => ['Apartment Owner'], 'Tenant' => ['Tenant'], 'Guard' => ['Guard']];
    }

    public function test_the_app_endpoint_needs_a_login_and_a_society(): void
    {
        $this->getJson('/api/v1/society/info')->assertUnauthorized();

        Sanctum::actingAs($this->makeUserWithRole($this->society, 'Super Admin', ['society_id' => null]));
        $this->getJson('/api/v1/society/info')->assertNotFound();
    }

    public function test_the_existing_documents_endpoints_are_untouched(): void
    {
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');
        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/society-documents')->assertOk()->assertJsonPath('success', true);
        $this->assertSame(
            ['success', 'data'],
            array_slice(array_keys($this->getJson('/api/v1/society-documents')->json()), 0, 2)
        );
    }
}
