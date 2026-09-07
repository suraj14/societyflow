<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LandingSetting;
use App\Models\LandingFeature;
use App\Models\LandingReview;
use App\Models\LandingFaq;
use App\Models\LandingPricing;

class LandingSiteSeeder extends Seeder
{
    public function run(): void
    {
        // Default Settings
        $settings = [
            ['key' => 'site_title', 'value' => 'SocietyFlow', 'type' => 'string'],
            ['key' => 'site_tagline', 'value' => 'Modern Society Management', 'type' => 'string'],
            ['key' => 'hero_title', 'value' => 'Simplify Your Society Management', 'type' => 'string'],
            ['key' => 'hero_subtitle', 'value' => 'Complete solution for managing residential societies, apartments, and villas with ease. Streamline operations, improve communication, and enhance resident experience.', 'type' => 'string'],
            ['key' => 'hero_button_text', 'value' => 'Get Started Free', 'type' => 'string'],
            ['key' => 'hero_button_url', 'value' => '/register', 'type' => 'string'],
            ['key' => 'contact_email', 'value' => 'support@societyflow.com', 'type' => 'string'],
            ['key' => 'contact_phone', 'value' => '+1 (555) 123-4567', 'type' => 'string'],
            ['key' => 'contact_address', 'value' => '123 Business Park, Tech City, TC 12345', 'type' => 'string'],
            ['key' => 'disable_landing_site', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            LandingSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        // Default Features
        $features = [
            ['type' => 'icon', 'icon' => 'fas fa-building', 'title' => 'Society Management', 'description' => 'Manage multiple societies, buildings, and units from a single dashboard with complete control.', 'sort_order' => 1],
            ['type' => 'icon', 'icon' => 'fas fa-users', 'title' => 'Resident Portal', 'description' => 'Dedicated portal for residents to manage their accounts, view bills, and submit requests.', 'sort_order' => 2],
            ['type' => 'icon', 'icon' => 'fas fa-credit-card', 'title' => 'Billing & Payments', 'description' => 'Automated billing, online payments, and comprehensive financial reporting.', 'sort_order' => 3],
            ['type' => 'icon', 'icon' => 'fas fa-tools', 'title' => 'Maintenance Requests', 'description' => 'Track and manage maintenance requests with real-time status updates.', 'sort_order' => 4],
            ['type' => 'icon', 'icon' => 'fas fa-user-shield', 'title' => 'Visitor Management', 'description' => 'Pre-approve visitors and track entry/exit with digital logs and notifications.', 'sort_order' => 5],
            ['type' => 'icon', 'icon' => 'fas fa-calendar-check', 'title' => 'Facility Booking', 'description' => 'Book amenities like clubhouse, gym, and swimming pool online with ease.', 'sort_order' => 6],
        ];

        foreach ($features as $feature) {
            LandingFeature::updateOrCreate(['title' => $feature['title']], $feature);
        }

        // Default Reviews
        $reviews = [
            ['name' => 'Rajesh Kumar', 'designation' => 'Society President', 'company' => 'Green Valley', 'review' => 'SocietyFlow has transformed how we manage our residential complex. The billing automation alone saves us hours every month.', 'rating' => 5, 'sort_order' => 1],
            ['name' => 'Priya Sharma', 'designation' => 'Secretary', 'company' => 'Sunrise Apartments', 'review' => 'The visitor management system is excellent. Our security team loves it, and residents feel safer knowing who\'s entering the premises.', 'rating' => 5, 'sort_order' => 2],
            ['name' => 'Amit Patel', 'designation' => 'Manager', 'company' => 'Palm Villas', 'review' => 'Best investment we made for our villa community. The support team is responsive and the features keep getting better.', 'rating' => 5, 'sort_order' => 3],
        ];

        foreach ($reviews as $review) {
            LandingReview::updateOrCreate(['name' => $review['name'], 'company' => $review['company']], $review);
        }

        // Default FAQs
        $faqs = [
            ['question' => 'How do I get started with SocietyFlow?', 'answer' => 'Simply sign up for a free trial, add your society details, and start inviting residents. Our onboarding wizard will guide you through the setup process.', 'sort_order' => 1],
            ['question' => 'Can I import existing resident data?', 'answer' => 'Yes! You can import resident data via CSV/Excel files. Our support team can also help with data migration from other systems.', 'sort_order' => 2],
            ['question' => 'Is my data secure?', 'answer' => 'Absolutely. We use industry-standard encryption, regular backups, and comply with data protection regulations to keep your data safe.', 'sort_order' => 3],
            ['question' => 'Can residents pay maintenance fees online?', 'answer' => 'Yes, residents can pay via multiple payment methods including credit/debit cards, UPI, and net banking. All transactions are secure and instant.', 'sort_order' => 4],
            ['question' => 'Do you offer customer support?', 'answer' => 'Yes, we offer email support for all plans, priority support for Professional plans, and 24/7 dedicated support for Enterprise customers.', 'sort_order' => 5],
        ];

        foreach ($faqs as $faq) {
            LandingFaq::updateOrCreate(['question' => $faq['question']], $faq);
        }

        // Default Pricing
        $pricing = [
            ['name' => 'Starter', 'description' => 'For small societies', 'monthly_price' => 29, 'yearly_price' => 290, 'features' => ['Up to 50 units', 'Basic features', 'Email support', 'Monthly reports'], 'is_popular' => false, 'sort_order' => 1],
            ['name' => 'Professional', 'description' => 'For growing societies', 'monthly_price' => 79, 'yearly_price' => 790, 'features' => ['Up to 200 units', 'All features', 'Priority support', 'Custom branding', 'API access'], 'is_popular' => true, 'sort_order' => 2],
            ['name' => 'Enterprise', 'description' => 'For large societies', 'monthly_price' => 199, 'yearly_price' => 1990, 'features' => ['Unlimited units', 'All features', '24/7 support', 'Dedicated manager', 'Custom integrations', 'SLA guarantee'], 'is_popular' => false, 'sort_order' => 3],
        ];

        foreach ($pricing as $plan) {
            LandingPricing::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
