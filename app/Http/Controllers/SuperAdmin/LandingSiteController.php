<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LandingSetting;
use App\Models\LandingFeature;
use App\Models\LandingReview;
use App\Models\LandingFaq;
use App\Models\LandingPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingSiteController extends Controller
{
    public function index()
    {
        $settings = LandingSetting::getAllSettings();
        $features = LandingFeature::orderBy('sort_order')->get();
        $reviews = LandingReview::orderBy('sort_order')->get();
        $faqs = LandingFaq::orderBy('sort_order')->get();
        $pricing = LandingPricing::orderBy('sort_order')->get();

        return view('super-admin.landing-site.index', compact(
            'settings', 'features', 'reviews', 'faqs', 'pricing'
        ));
    }

    // Toggle Landing Site
    public function toggleLanding(Request $request)
    {
        LandingSetting::set('disable_landing_site', $request->boolean('disable'), 'boolean');
        return back()->with('success', 'Landing site status updated successfully.');
    }

    // Header Settings
    public function updateHeader(Request $request)
    {
        $request->validate([
            'site_title' => 'nullable|string|max:255',
            'site_tagline' => 'nullable|string|max:500',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'hero_button_text' => 'nullable|string|max:100',
            'hero_button_url' => 'nullable|string|max:255',
            'hero_image' => 'nullable|file|max:2048',
        ]);

        foreach (['site_title', 'site_tagline', 'hero_title', 'hero_subtitle', 'hero_button_text', 'hero_button_url'] as $key) {
            if ($request->has($key)) {
                LandingSetting::set($key, $request->input($key));
            }
        }

        if ($request->hasFile('hero_image')) {
            $path = $request->file('hero_image')->store('landing', 'public');
            LandingSetting::set('hero_image', $path, 'file');
        }

        return back()->with('success', 'Header settings updated successfully.');
    }


    // Feature Management
    public function storeFeature(Request $request)
    {
        $request->validate([
            'type' => 'required|in:icon,image',
            'icon' => 'required_if:type,icon|nullable|string|max:100',
            'image' => 'required_if:type,image|nullable|file|max:2048',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $data = $request->only(['type', 'icon', 'title', 'description']);
        $data['sort_order'] = LandingFeature::max('sort_order') + 1;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('landing/features', 'public');
        }

        LandingFeature::create($data);
        return back()->with('success', 'Feature added successfully.');
    }

    public function updateFeature(Request $request, LandingFeature $feature)
    {
        $request->validate([
            'type' => 'required|in:icon,image',
            'icon' => 'required_if:type,icon|nullable|string|max:100',
            'image' => 'nullable|file|max:2048',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $data = $request->only(['type', 'icon', 'title', 'description']);

        if ($request->hasFile('image')) {
            if ($feature->image) {
                Storage::disk('public')->delete($feature->image);
            }
            $data['image'] = $request->file('image')->store('landing/features', 'public');
        }

        $feature->update($data);
        return back()->with('success', 'Feature updated successfully.');
    }

    public function deleteFeature(LandingFeature $feature)
    {
        if ($feature->image) {
            Storage::disk('public')->delete($feature->image);
        }
        $feature->delete();
        return back()->with('success', 'Feature deleted successfully.');
    }

    public function toggleFeature(LandingFeature $feature)
    {
        $feature->update(['is_active' => !$feature->is_active]);
        return back()->with('success', 'Feature status updated.');
    }

    // Review Management
    public function storeReview(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'avatar' => 'nullable|file|max:1024',
            'review' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $data = $request->only(['name', 'designation', 'company', 'review', 'rating']);
        $data['sort_order'] = LandingReview::max('sort_order') + 1;

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('landing/reviews', 'public');
        }

        LandingReview::create($data);
        return back()->with('success', 'Review added successfully.');
    }

    public function updateReview(Request $request, LandingReview $review)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'avatar' => 'nullable|file|max:1024',
            'review' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $data = $request->only(['name', 'designation', 'company', 'review', 'rating']);

        if ($request->hasFile('avatar')) {
            if ($review->avatar) {
                Storage::disk('public')->delete($review->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('landing/reviews', 'public');
        }

        $review->update($data);
        return back()->with('success', 'Review updated successfully.');
    }

    public function deleteReview(LandingReview $review)
    {
        if ($review->avatar) {
            Storage::disk('public')->delete($review->avatar);
        }
        $review->delete();
        return back()->with('success', 'Review deleted successfully.');
    }

    public function toggleReview(LandingReview $review)
    {
        $review->update(['is_active' => !$review->is_active]);
        return back()->with('success', 'Review status updated.');
    }


    // FAQ Management
    public function storeFaq(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:2000',
        ]);

        LandingFaq::create([
            'question' => $request->question,
            'answer' => $request->answer,
            'sort_order' => LandingFaq::max('sort_order') + 1,
        ]);

        return back()->with('success', 'FAQ added successfully.');
    }

    public function updateFaq(Request $request, LandingFaq $faq)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:2000',
        ]);

        $faq->update($request->only(['question', 'answer']));
        return back()->with('success', 'FAQ updated successfully.');
    }

    public function deleteFaq(LandingFaq $faq)
    {
        $faq->delete();
        return back()->with('success', 'FAQ deleted successfully.');
    }

    public function toggleFaq(LandingFaq $faq)
    {
        $faq->update(['is_active' => !$faq->is_active]);
        return back()->with('success', 'FAQ status updated.');
    }

    // Contact Settings
    public function updateContact(Request $request)
    {
        $request->validate([
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string|max:500',
            'contact_map_embed' => 'nullable|string|max:2000',
            'social_facebook' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',
        ]);

        foreach ($request->only([
            'contact_email', 'contact_phone', 'contact_address', 'contact_map_embed',
            'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin'
        ]) as $key => $value) {
            LandingSetting::set($key, $value);
        }

        return back()->with('success', 'Contact settings updated successfully.');
    }

    // Pricing Management
    public function storePricing(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'features' => 'nullable|array',
            'is_popular' => 'boolean',
        ]);

        LandingPricing::create([
            'name' => $request->name,
            'description' => $request->description,
            'monthly_price' => $request->monthly_price,
            'yearly_price' => $request->yearly_price,
            'features' => $request->features,
            'is_popular' => $request->boolean('is_popular'),
            'sort_order' => LandingPricing::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Pricing plan added successfully.');
    }

    public function updatePricing(Request $request, LandingPricing $pricing)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'features' => 'nullable|array',
            'is_popular' => 'boolean',
        ]);

        $pricing->update([
            'name' => $request->name,
            'description' => $request->description,
            'monthly_price' => $request->monthly_price,
            'yearly_price' => $request->yearly_price,
            'features' => $request->features,
            'is_popular' => $request->boolean('is_popular'),
        ]);

        return back()->with('success', 'Pricing plan updated successfully.');
    }

    public function deletePricing(LandingPricing $pricing)
    {
        $pricing->delete();
        return back()->with('success', 'Pricing plan deleted successfully.');
    }

    public function togglePricing(LandingPricing $pricing)
    {
        $pricing->update(['is_active' => !$pricing->is_active]);
        return back()->with('success', 'Pricing status updated.');
    }

    // Reorder items
    public function reorder(Request $request, string $type)
    {
        $request->validate(['items' => 'required|array']);

        $model = match($type) {
            'features' => LandingFeature::class,
            'reviews' => LandingReview::class,
            'faqs' => LandingFaq::class,
            'pricing' => LandingPricing::class,
            default => null,
        };

        if ($model) {
            foreach ($request->items as $index => $id) {
                $model::where('id', $id)->update(['sort_order' => $index]);
            }
        }

        return response()->json(['success' => true]);
    }
}
