<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Onboarding of several independent societies through the public registration form: names may repeat (the address of each society must
 * not), a half-finished registration leaves nothing behind, and the visitor never sees a database message.
 */
class SocietyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $society, string $email, string $type = 'apartment')
    {
        $this->makeRole('Admin');

        return $this->post(route('register.submit'), [
            'society_name' => $society, 'name' => 'Admin ' . $society, 'email' => $email, 'phone' => '9000000001',
            'password' => 'Xk9#mP2vQ7zL!a', 'password_confirmation' => 'Xk9#mP2vQ7zL!a', 'terms' => '1', 'property_type' => $type,
        ]);
    }

    public function test_two_societies_with_the_same_name_both_register_with_their_own_address_and_admin(): void
    {
        $this->register('Green Valley', 'one@example.test')->assertRedirect(route('login'));
        $this->register('Green Valley', 'two@example.test', 'villa')->assertRedirect(route('login'));
        $this->register('green  valley', 'three@example.test')->assertRedirect(route('login'));

        $societies = Society::orderBy('id')->get();
        $this->assertCount(3, $societies);
        $this->assertSame(['green-valley', 'green-valley-2', 'green-valley-3'], $societies->pluck('slug')->all());
        $this->assertSame($societies->pluck('slug')->all(), $societies->pluck('subdomain')->all(), 'subdomain follows the slug');
        foreach ($societies as $society) {
            $admin = User::find($society->admin_id);
            $this->assertSame($society->id, $admin->society_id);
            $this->assertTrue($admin->hasRole('Admin'));
            $this->assertSame(1, User::where('society_id', $society->id)->count(), 'each society starts with exactly its own admin');
            $this->assertSame(1, Subscription::where('society_id', $society->id)->count());
        }
    }

    public function test_a_duplicate_admin_email_is_refused_without_creating_a_society(): void
    {
        $this->register('Alpha', 'same@example.test')->assertRedirect(route('login'));
        $this->register('Beta', 'SAME@example.test')->assertSessionHasErrors('email');

        $this->assertSame(1, Society::count());
        $this->assertSame(1, User::count());
    }

    public function test_a_failure_halfway_rolls_everything_back_and_shows_no_database_detail(): void
    {
        Subscription::creating(fn () => throw new \RuntimeException('SQLSTATE[23000]: UNIQUE constraint failed: subscriptions.secret_column'));

        $response = $this->register('Failing Heights', 'fail@example.test');

        $response->assertSessionHasErrors('error');
        $message = session('errors')->first('error');
        $this->assertStringNotContainsString('SQLSTATE', $message);
        $this->assertStringNotContainsString('secret_column', $message);
        $this->assertSame(0, Society::count(), 'the society was rolled back');
        $this->assertSame(0, User::count(), 'and so was its admin');
    }

    public function test_renaming_keeps_a_societys_own_slug_and_never_takes_anothers(): void
    {
        $a = Society::create(['name' => 'Lake View', 'slug' => 'lake-view', 'email' => 'a@example.test', 'phone' => '9000000001', 'address' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '000000', 'country' => 'India']);
        $b = Society::create(['name' => 'Hill View', 'slug' => 'hill-view', 'email' => 'b@example.test', 'phone' => '9000000002', 'address' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '000000', 'country' => 'India']);

        $this->assertSame('lake-view', Society::uniqueSlug('Lake View', $a->id), 'its own address is not "taken"');
        $this->assertSame('lake-view-2', Society::uniqueSlug('Lake View', $b->id), 'another society\'s address is');
    }

    public function test_a_new_society_can_file_a_complaint_because_it_gets_default_categories_on_first_use(): void
    {
        $society = $this->makeSociety('Fresh Heights');
        $owner = $this->makeUserWithRole($society, 'Owner');
        $this->assertSame(0, \App\Models\ComplaintCategory::where('society_id', $society->id)->count());

        $page = $this->actingAs($owner)->get(route('complaints.create'))->assertOk()->getContent();
        $this->assertGreaterThan(5, preg_match_all('/<option value="\d+"/', $page), 'the form offers categories');

        $names = \Laravel\Sanctum\Sanctum::actingAs($owner);
        $this->getJson('/api/v1/complaints/categories')->assertOk()->assertJsonFragment(['Plumbing']);
        $this->assertSame(count(\App\Models\ComplaintCategory::DEFAULTS), \App\Models\ComplaintCategory::where('society_id', $society->id)->count(), 'created once, not per request');
    }

    public function test_defaults_never_touch_a_society_that_already_has_categories_or_another_society(): void
    {
        $mine = $this->makeSociety('Has Own');
        $other = $this->makeSociety('Other One');
        \App\Models\ComplaintCategory::create(['society_id' => $mine->id, 'name' => 'Only This', 'status' => 'active']);

        \App\Models\ComplaintCategory::ensureDefaults($mine->id);

        $this->assertSame(['Only This'], \App\Models\ComplaintCategory::where('society_id', $mine->id)->pluck('name')->all());
        $this->assertSame(0, \App\Models\ComplaintCategory::where('society_id', $other->id)->count(), 'nothing leaks into a society that was not asked for');
    }

    public function test_a_concurrent_registration_taking_the_same_address_is_retried_not_failed(): void
    {
        $raced = false;
        // runs after the model's own creating hook has chosen the slug: a second visitor "wins" that exact address first
        Society::creating(function ($society) use (&$raced) {
            if (!$raced) {
                $raced = true;
                \Illuminate\Support\Facades\DB::table('societies')->insert([
                    'name' => 'Rival', 'slug' => $society->slug, 'subdomain' => $society->slug, 'email' => 'rival@example.test', 'phone' => '9000000009',
                    'address' => 'x', 'city' => 'x', 'state' => 'x', 'country' => 'India', 'pincode' => '000000', 'public_id' => (string) \Illuminate\Support\Str::ulid(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->register('Racing Heights', 'race@example.test')->assertRedirect(route('login'));

        $mine = Society::where('name', 'Racing Heights')->firstOrFail();
        $this->assertSame('racing-heights-2', $mine->slug, 'the retry found the rival and took the next suffix');
        $this->assertSame(1, User::where('email', 'race@example.test')->count(), 'exactly one admin, no orphan from the failed first try');
    }

    public function test_default_categories_are_created_once_even_when_two_first_requests_overlap(): void
    {
        $society = $this->makeSociety('Overlap');
        $held = \Illuminate\Support\Facades\Cache::lock('complaint-categories-defaults:' . $society->id, 10);
        $this->assertTrue($held->get());

        \App\Models\ComplaintCategory::ensureDefaults($society->id);   // the "second" request: waits, gives up, adds nothing, does not fail
        $this->assertSame(0, \App\Models\ComplaintCategory::where('society_id', $society->id)->count());

        $held->release();
        \App\Models\ComplaintCategory::ensureDefaults($society->id);
        \App\Models\ComplaintCategory::ensureDefaults($society->id);
        $this->assertSame(count(\App\Models\ComplaintCategory::DEFAULTS), \App\Models\ComplaintCategory::where('society_id', $society->id)->count());
    }
}