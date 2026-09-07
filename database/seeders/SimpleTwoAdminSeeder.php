<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Society;
use App\Models\SystemSetting;
use Spatie\Permission\Models\Role;

class SimpleTwoAdminSeeder extends Seeder
{
    public function run()
    {
        // Clean existing data for these specific emails
        User::whereIn('email', ['admin@societyflow.com', 'sahgunvilla@gmail.com'])->delete();
        
        // Find or create societies
        $society1 = Society::firstOrCreate(
            ['slug' => 'urban-heights-residency'],
            [
                'name' => 'Urban Heights Residency',
                'subdomain' => 'urbanheights',
                'address' => '123 Metropolitan Avenue, Downtown District, Mumbai, Maharashtra, India',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'phone' => '+91-9876543210',
                'email' => 'info@urbanheights.com',
                'website' => 'www.urbanheights.com',
                'description' => 'Premium residential complex with modern amenities and excellent connectivity.',
                'status' => 'active',
                'trial_ends_at' => now()->addYear(),
            ]
        );

        $society2 = Society::firstOrCreate(
            ['slug' => 'sahgun-villa-estate'],
            [
                'name' => 'Sahgun Villa Estate',
                'subdomain' => 'sahgunvilla',
                'address' => '456 Garden Valley Road, Green Hills, Pune, Maharashtra, India',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '411001',
                'phone' => '+91-8765432109',
                'email' => 'contact@sahgunvilla.com',
                'website' => 'www.sahgunvilla.com',
                'description' => 'Luxury villa estate with spacious homes and premium lifestyle amenities.',
                'status' => 'active',
                'trial_ends_at' => now()->addYear(),
            ]
        );

        // Create Admin Users
        $adminRole = Role::where('name', 'Admin')->first();

        $admin1 = User::create([
            'name' => 'Rajesh Kumar',
            'email' => 'admin@societyflow.com',
            'password' => Hash::make('password'),
            'phone' => '+91-9876543210',
            'society_id' => $society1->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin1->assignRole($adminRole);

        $admin2 = User::create([
            'name' => 'Priya Sharma',
            'email' => 'sahgunvilla@gmail.com',
            'password' => Hash::make('password'),
            'phone' => '+91-8765432109',
            'society_id' => $society2->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin2->assignRole($adminRole);

        // Update societies with admin_id
        $society1->update(['admin_id' => $admin1->id]);
        $society2->update(['admin_id' => $admin2->id]);

        // Create different settings for both societies
        $this->createSettings($society1, $society2);

        echo "✅ Two admin accounts created successfully!\n";
        echo "🏢 Urban Heights Admin: admin@societyflow.com / password\n";
        echo "🏡 Sahgun Villa Admin: sahgunvilla@gmail.com / password\n";
    }

    private function createSettings($society1, $society2)
    {
        // Clear existing settings for these societies
        SystemSetting::whereIn('society_id', [$society1->id, $society2->id])->delete();

        // Settings for Urban Heights (Modern Corporate Style)
        SystemSetting::set('app', 'society_logo', 'urban-heights-logo.png', 'string', $society1->id);
        SystemSetting::set('app', 'society_tagline', 'Modern Living, Premium Lifestyle', 'string', $society1->id);
        SystemSetting::set('app', 'app_name', 'Urban Heights Connect', 'string', $society1->id);
        SystemSetting::set('theme', 'primary_color', '#2563eb', 'string', $society1->id);
        SystemSetting::set('theme', 'secondary_color', '#64748b', 'string', $society1->id);
        SystemSetting::set('theme', 'accent_color', '#f59e0b', 'string', $society1->id);
        SystemSetting::set('theme', 'sidebar_theme', 'light', 'string', $society1->id);
        SystemSetting::set('app', 'date_format', 'd/m/Y', 'string', $society1->id);
        SystemSetting::set('app', 'time_format', '24', 'string', $society1->id);
        SystemSetting::set('email', 'smtp_host', 'smtp.gmail.com', 'string', $society1->id);
        SystemSetting::set('payment', 'razorpay_enabled', '1', 'boolean', $society1->id);
        SystemSetting::set('payment', 'stripe_enabled', '1', 'boolean', $society1->id);

        // Settings for Sahgun Villa (Luxury Premium Style)
        SystemSetting::set('app', 'society_logo', 'sahgun-villa-logo.png', 'string', $society2->id);
        SystemSetting::set('app', 'society_tagline', 'Luxury Redefined, Nature Embraced', 'string', $society2->id);
        SystemSetting::set('app', 'app_name', 'Sahgun Villa Portal', 'string', $society2->id);
        SystemSetting::set('theme', 'primary_color', '#059669', 'string', $society2->id);
        SystemSetting::set('theme', 'secondary_color', '#78716c', 'string', $society2->id);
        SystemSetting::set('theme', 'accent_color', '#dc2626', 'string', $society2->id);
        SystemSetting::set('theme', 'sidebar_theme', 'light', 'string', $society2->id);
        SystemSetting::set('app', 'date_format', 'M d, Y', 'string', $society2->id);
        SystemSetting::set('app', 'time_format', '12', 'string', $society2->id);
        SystemSetting::set('email', 'smtp_host', 'smtp.outlook.com', 'string', $society2->id);
        SystemSetting::set('payment', 'razorpay_enabled', '1', 'boolean', $society2->id);
        SystemSetting::set('payment', 'paypal_enabled', '1', 'boolean', $society2->id);
    }
}