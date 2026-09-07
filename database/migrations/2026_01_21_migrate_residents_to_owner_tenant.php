<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\Resident;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            // Migrate Residents with type='owner' to Owner model
            $ownerResidents = Resident::where('type', 'owner')->get();
            
            foreach ($ownerResidents as $resident) {
                // Create Owner record
                $owner = Owner::create([
                    'society_id' => $resident->society_id,
                    'name' => $resident->name,
                    'email' => $resident->email,
                    'phone' => $resident->phone,
                    'status' => $resident->status,
                ]);

                // Update flat owner_id
                if ($resident->flat_id) {
                    DB::table('flats')
                        ->where('id', $resident->flat_id)
                        ->update(['owner_id' => $owner->id]);
                }

                // Create or update User with owner_id
                if ($resident->user_id) {
                    User::where('id', $resident->user_id)
                        ->update(['owner_id' => $owner->id]);
                }
            }

            // Migrate Residents with type='tenant' to Tenant model
            $tenantResidents = Resident::where('type', 'tenant')->get();
            
            foreach ($tenantResidents as $resident) {
                // Get owner for this flat
                $flat = DB::table('flats')->find($resident->flat_id);
                $ownerId = $flat ? $flat->owner_id : null;

                // Create Tenant record
                $tenant = Tenant::create([
                    'society_id' => $resident->society_id,
                    'flat_id' => $resident->flat_id,
                    'owner_id' => $ownerId,
                    'name' => $resident->name,
                    'email' => $resident->email,
                    'phone' => $resident->phone,
                    'move_in_date' => $resident->move_in_date,
                    'move_out_date' => $resident->move_out_date,
                    'monthly_rent' => $resident->monthly_rent,
                    'security_deposit' => $resident->security_deposit,
                    'status' => $resident->status,
                ]);

                // Create or update User with tenant_id
                if ($resident->user_id) {
                    User::where('id', $resident->user_id)
                        ->update(['tenant_id' => $tenant->id]);
                }
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            // Delete all Owner and Tenant records created during migration
            Owner::whereNotNull('id')->delete();
            Tenant::whereNotNull('id')->delete();
            
            // Reset user owner_id and tenant_id
            User::whereNotNull('owner_id')->update(['owner_id' => null]);
            User::whereNotNull('tenant_id')->update(['tenant_id' => null]);
        } finally {
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};

