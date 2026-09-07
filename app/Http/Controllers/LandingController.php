<?php

namespace App\Http\Controllers;

use App\Models\LandingSetting;
use App\Models\LandingFeature;
use App\Models\LandingReview;
use App\Models\LandingFaq;
use App\Models\LandingPricing;

class LandingController extends Controller
{
    public function index()
    {
        // Check if landing site is disabled
        if (LandingSetting::isLandingDisabled()) {
            return redirect()->route('login');
        }

        $settings = LandingSetting::getAllSettings();
        $features = LandingFeature::where('is_active', true)->orderBy('sort_order')->get();
        $reviews = LandingReview::where('is_active', true)->orderBy('sort_order')->get();
        $faqs = LandingFaq::where('is_active', true)->orderBy('sort_order')->get();
        $pricing = LandingPricing::where('is_active', true)->orderBy('sort_order')->get();

        return view('landing', compact('settings', 'features', 'reviews', 'faqs', 'pricing'));
    }
}
