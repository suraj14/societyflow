<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete all services and recreate them fresh
        DB::table('services')->truncate();
        
        // Recreate services with unique entries
        $services = [
            ['name' => 'Maid', 'category' => 'daily', 'icon' => 'fas fa-broom', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 1],
            ['name' => 'Cook', 'category' => 'daily', 'icon' => 'fas fa-utensils', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 2],
            ['name' => 'Driver', 'category' => 'daily', 'icon' => 'fas fa-car', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 3],
            ['name' => 'Nanny / Babysitter', 'category' => 'daily', 'icon' => 'fas fa-baby', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 4],
            ['name' => 'House Help', 'category' => 'daily', 'icon' => 'fas fa-hands-helping', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 5],
            ['name' => 'Electrician', 'category' => 'maintenance', 'icon' => 'fas fa-bolt', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 10],
            ['name' => 'Plumber', 'category' => 'maintenance', 'icon' => 'fas fa-wrench', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 11],
            ['name' => 'Carpenter', 'category' => 'maintenance', 'icon' => 'fas fa-hammer', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 12],
            ['name' => 'AC Service', 'category' => 'maintenance', 'icon' => 'fas fa-snowflake', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 13],
            ['name' => 'Pest Control', 'category' => 'maintenance', 'icon' => 'fas fa-bug', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 14],
            ['name' => 'Security Staff', 'category' => 'society', 'icon' => 'fas fa-shield-alt', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 20],
            ['name' => 'Gardener', 'category' => 'society', 'icon' => 'fas fa-seedling', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 21],
            ['name' => 'Cleaner', 'category' => 'society', 'icon' => 'fas fa-spray-can', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 22],
            ['name' => 'Lift Technician', 'category' => 'society', 'icon' => 'fas fa-elevator', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 23],
            ['name' => 'Milkman', 'category' => 'optional', 'icon' => 'fas fa-glass-whiskey', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 30],
            ['name' => 'Newspaper', 'category' => 'optional', 'icon' => 'fas fa-newspaper', 'is_enabled' => true, 'requires_attendance' => true, 'sort_order' => 31],
            ['name' => 'Internet / Cable Service', 'category' => 'optional', 'icon' => 'fas fa-wifi', 'is_enabled' => true, 'requires_attendance' => false, 'sort_order' => 32],
        ];
        
        foreach ($services as $service) {
            DB::table('services')->insert(array_merge($service, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('services')->truncate();
    }
};
