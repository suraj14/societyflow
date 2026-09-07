<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, we need to recreate the table since it doesn't support modifying enum constraints
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Get all existing events
            $events = DB::table('events')->get();

            // Drop the old table
            Schema::dropIfExists('events');

            // Recreate with new enum
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('society_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('event_name');
                $table->string('location');
                $table->text('description');
                $table->dateTime('start_date');
                $table->dateTime('end_date');
                $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
                $table->boolean('is_role_based')->default(true);
                $table->json('visible_roles')->nullable();
                $table->json('visible_users')->nullable();
                $table->timestamps();
                
                $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index('society_id');
                $table->index('status');
            });

            // Restore the data, converting 'approved' to 'completed'
            foreach ($events as $event) {
                $status = $event->status === 'approved' ? 'completed' : $event->status;
                DB::table('events')->insert([
                    'id' => $event->id,
                    'society_id' => $event->society_id,
                    'created_by' => $event->created_by,
                    'event_name' => $event->event_name,
                    'location' => $event->location,
                    'description' => $event->description,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'status' => $status,
                    'is_role_based' => $event->is_role_based,
                    'visible_roles' => $event->visible_roles,
                    'visible_users' => $event->visible_users,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at,
                ]);
            }
        } else {
            // For other databases, use ALTER TABLE
            DB::statement("ALTER TABLE events MODIFY status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending'");
            
            // Update existing 'approved' values to 'completed'
            DB::table('events')->where('status', 'approved')->update(['status' => 'completed']);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Get all existing events
            $events = DB::table('events')->get();

            // Drop the new table
            Schema::dropIfExists('events');

            // Recreate with old enum
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('society_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('event_name');
                $table->string('location');
                $table->text('description');
                $table->dateTime('start_date');
                $table->dateTime('end_date');
                $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
                $table->boolean('is_role_based')->default(true);
                $table->json('visible_roles')->nullable();
                $table->json('visible_users')->nullable();
                $table->timestamps();
                
                $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index('society_id');
                $table->index('status');
            });

            // Restore the data, converting 'completed' back to 'approved'
            foreach ($events as $event) {
                $status = $event->status === 'completed' ? 'approved' : $event->status;
                DB::table('events')->insert([
                    'id' => $event->id,
                    'society_id' => $event->society_id,
                    'created_by' => $event->created_by,
                    'event_name' => $event->event_name,
                    'location' => $event->location,
                    'description' => $event->description,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'status' => $status,
                    'is_role_based' => $event->is_role_based,
                    'visible_roles' => $event->visible_roles,
                    'visible_users' => $event->visible_users,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at,
                ]);
            }
        } else {
            // For other databases, use ALTER TABLE
            DB::statement("ALTER TABLE events MODIFY status ENUM('pending', 'approved', 'cancelled') DEFAULT 'pending'");
            
            // Update existing 'completed' values back to 'approved'
            DB::table('events')->where('status', 'completed')->update(['status' => 'approved']);
        }
    }
};
