<?php

namespace Tests\Feature;

use App\Models\LandingFaq;
use App\Models\LandingFeature;
use App\Models\LandingPricing;
use App\Models\LandingReview;
use App\Models\LandingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Super Admin > Landing Site editor (24 mutating routes, none referenced by a test). Real payloads for
 * every content type, what the public landing page shows afterwards, and the upload rules: the three
 * image fields took ANY file type onto the public disk (`file|max` only) - a script or HTML page
 * stored under the web root.
 */
class LandingSiteEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->super = $this->makeUserWithRole($this->makeSociety('Eden'), 'Super Admin', ['society_id' => null]);
    }

    private function png(string $name = 'x.png'): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('lp_') . '.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function disguised(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('bad_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function as(): self
    {
        return $this->actingAs($this->super);
    }

    // ------------------------------------------------------------ settings

    public function test_the_landing_site_can_be_switched_off_and_on(): void
    {
        $this->as()->post(route('super-admin.landing-site.toggle'), ['disable' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) LandingSetting::get('disable_landing_site'));

        $this->as()->post(route('super-admin.landing-site.toggle'), ['disable' => 0]);
        $this->assertFalse((bool) LandingSetting::get('disable_landing_site'));
    }

    public function test_header_and_contact_settings_save_and_only_the_sent_fields_change(): void
    {
        $this->as()->post(route('super-admin.landing-site.header'), ['site_title' => 'SocietyFlow', 'hero_title' => 'Run your society'])->assertSessionHas('success');
        $this->as()->post(route('super-admin.landing-site.header'), ['hero_subtitle' => 'Simple']);
        $this->assertSame('SocietyFlow', LandingSetting::get('site_title'));
        $this->assertSame('Run your society', LandingSetting::get('hero_title'), 'a later partial save does not erase earlier fields');
        $this->assertSame('Simple', LandingSetting::get('hero_subtitle'));

        $this->as()->post(route('super-admin.landing-site.contact'), ['contact_email' => 'hello@example.test', 'social_facebook' => 'https://facebook.example.test/p'])->assertSessionHas('success');
        $this->assertSame('hello@example.test', LandingSetting::get('contact_email'));

        $this->as()->post(route('super-admin.landing-site.contact'), ['contact_email' => 'not-an-email', 'social_twitter' => 'javascript:alert(1)'])->assertSessionHasErrors(['contact_email', 'social_twitter']);
        $this->assertSame('hello@example.test', LandingSetting::get('contact_email'), 'a refused save changes nothing');
    }

    // ------------------------------------------------------------ uploads

    public function test_only_real_images_are_accepted_for_the_hero_feature_and_review_pictures(): void
    {
        $bad = [
            'php script' => $this->disguised('shell.php', '<?php system($_GET["c"]);'),
            'html page'  => $this->disguised('page.html', '<html><script>alert(1)</script></html>'),
            'php as png' => $this->disguised('shell.png', '<?php echo 1;'),
        ];

        foreach ($bad as $label => $file) {
            $this->as()->post(route('super-admin.landing-site.header'), ['hero_image' => $file])->assertSessionHasErrors('hero_image');
            $this->as()->post(route('super-admin.landing-site.features.store'), ['type' => 'image', 'title' => 'T', 'image' => $file])->assertSessionHasErrors('image');
            $this->as()->post(route('super-admin.landing-site.reviews.store'), ['name' => 'N', 'review' => 'R', 'rating' => 5, 'avatar' => $file])->assertSessionHasErrors('avatar');
        }
        $this->assertSame([], Storage::disk('public')->allFiles(), "no refused file was written ({$label})");
        $this->assertSame(0, LandingFeature::count() + LandingReview::count());
    }

    public function test_a_real_image_is_stored_and_replaced_cleanly(): void
    {
        $this->as()->post(route('super-admin.landing-site.features.store'), ['type' => 'image', 'title' => 'Pic', 'image' => $this->png()])->assertSessionHas('success');
        $feature = LandingFeature::firstOrFail();
        Storage::disk('public')->assertExists($feature->image);
        $old = $feature->image;

        $this->as()->put(route('super-admin.landing-site.features.update', $feature), ['type' => 'image', 'title' => 'Pic 2', 'image' => $this->png('y.png')]);
        $feature->refresh();
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($feature->image);

        $this->as()->delete(route('super-admin.landing-site.features.destroy', $feature));
        $this->assertSame([], Storage::disk('public')->allFiles(), 'deleting a feature removes its picture');
    }

    // ------------------------------------------------------------ content CRUD

    public function test_features_reviews_faqs_and_pricing_can_be_created_edited_toggled_and_deleted(): void
    {
        // features (icon type needs an icon)
        $this->as()->post(route('super-admin.landing-site.features.store'), ['type' => 'icon', 'title' => 'Visitors'])->assertSessionHasErrors('icon');
        $this->as()->post(route('super-admin.landing-site.features.store'), ['type' => 'icon', 'icon' => 'fa-user', 'title' => 'Visitors']);
        $feature = LandingFeature::firstOrFail();
        $this->as()->put(route('super-admin.landing-site.features.update', $feature), ['type' => 'icon', 'icon' => 'fa-bell', 'title' => 'Alerts']);
        $this->assertSame('Alerts', $feature->fresh()->title);

        // reviews (rating 1-5)
        $this->as()->post(route('super-admin.landing-site.reviews.store'), ['name' => 'Asha', 'review' => 'Great', 'rating' => 9])->assertSessionHasErrors('rating');
        $this->as()->post(route('super-admin.landing-site.reviews.store'), ['name' => 'Asha', 'review' => 'Great', 'rating' => 5]);
        $review = LandingReview::firstOrFail();
        $this->as()->put(route('super-admin.landing-site.reviews.update', $review), ['name' => 'Asha K', 'review' => 'Excellent', 'rating' => 4]);
        $this->assertSame(4, (int) $review->fresh()->rating);

        // faqs
        $this->as()->post(route('super-admin.landing-site.faqs.store'), ['question' => 'Cost?', 'answer' => 'Free']);
        $faq = LandingFaq::firstOrFail();
        $this->as()->put(route('super-admin.landing-site.faqs.update', $faq), ['question' => 'Cost?', 'answer' => 'Free forever']);
        $this->assertSame('Free forever', $faq->fresh()->answer);

        // pricing (features list, popular flag)
        $this->as()->post(route('super-admin.landing-site.pricing.store'), ['name' => 'Pro', 'monthly_price' => 999, 'yearly_price' => 9999, 'features' => ['A', 'B'], 'is_popular' => 1]);
        $plan = LandingPricing::firstOrFail();
        $this->assertSame(['A', 'B'], $plan->features);
        $this->assertTrue((bool) $plan->is_popular);

        // toggle flips each way
        foreach ([[$feature, 'features'], [$review, 'reviews'], [$faq, 'faqs'], [$plan, 'pricing']] as [$model, $route]) {
            $before = (bool) $model->fresh()->is_active;
            $this->as()->post(route("super-admin.landing-site.{$route}.toggle", $model));
            $this->assertSame(!$before, (bool) $model->fresh()->is_active, $route);
        }

        // delete
        $this->as()->delete(route('super-admin.landing-site.pricing.destroy', $plan));
        $this->as()->delete(route('super-admin.landing-site.faqs.destroy', $faq));
        $this->as()->delete(route('super-admin.landing-site.reviews.destroy', $review));
        $this->as()->delete(route('super-admin.landing-site.features.destroy', $feature));
        $this->assertSame(0, LandingPricing::count() + LandingFaq::count() + LandingReview::count() + LandingFeature::count());
    }

    public function test_reorder_sets_the_order_and_refuses_junk(): void
    {
        $a = LandingFaq::create(['question' => 'A', 'answer' => 'a', 'sort_order' => 0]);
        $b = LandingFaq::create(['question' => 'B', 'answer' => 'b', 'sort_order' => 1]);

        $this->as()->postJson(route('super-admin.landing-site.reorder', 'faqs'), ['items' => [$b->id, $a->id]])->assertOk()->assertJson(['success' => true]);
        $this->assertSame([$b->id, $a->id], LandingFaq::orderBy('sort_order')->pluck('id')->all());

        $this->as()->postJson(route('super-admin.landing-site.reorder', 'faqs'), ['items' => [[1], 'x']])->assertStatus(422);
        $this->as()->postJson(route('super-admin.landing-site.reorder', 'faqs'), [])->assertStatus(422);
    }

    // ------------------------------------------------------------ public page

    public function test_the_public_landing_page_shows_active_content_and_hides_switched_off_content(): void
    {
        LandingFaq::create(['question' => 'Visible question', 'answer' => 'v', 'sort_order' => 0, 'is_active' => true]);
        LandingFaq::create(['question' => 'Hidden question', 'answer' => 'h', 'sort_order' => 1, 'is_active' => false]);

        $this->get('/')->assertOk()->assertSee('Visible question')->assertDontSee('Hidden question');
    }
}
