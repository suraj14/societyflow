<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('society_id');
            $table->unsignedBigInteger('flat_id');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->decimal('monthly_rent', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->json('emergency_contact')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('society_id')->references('id')->on('societies')->onDelete('cascade');
            $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('owners')->onDelete('set null');

            // Indexes
            $table->index('society_id');
            $table->index('flat_id');
            $table->index('owner_id');
            $table->index('status');
        });

        // users.tenant_id FK — deferred here because add_owner_tenant_to_users
        // sorts before this migration, when tenants doesn't exist yet.
        if (Schema::hasColumn('users', 'tenant_id')) {
            $fks = $this->existingForeignKeys('users');
            if (!$fks->contains('users_tenant_id_foreign')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
                });
            }
        }
    }

    /**
     * Names of the foreign key constraints already on $table.
     *
     * information_schema is MySQL-only. SQLite cannot ALTER TABLE ADD
     * CONSTRAINT (Laravel's SQLite grammar compiles ->foreign() to a no-op),
     * so an empty list is correct there.
     */
    private function existingForeignKeys(string $table): \Illuminate\Support\Collection
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return collect();
        }

        return collect($connection->select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$table]
        ))->pluck('CONSTRAINT_NAME');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
