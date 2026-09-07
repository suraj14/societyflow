<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards the contract between what controllers accept and what the database
 * can actually store.
 *
 * Every P0 in this class was a real production 500: a validation rule allowed
 * a value the column constraint rejected, or a controller wrote to a table or
 * column that did not exist. These are cheap to assert and impossible to spot
 * by reading either side alone.
 */
class SchemaContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every visitor_type the API validates must be storable.
     *
     * VisitorApiController::store allowed 11 types while the column
     * constraint allowed 5.
     */
    public function test_every_accepted_visitor_type_can_be_stored(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        Sanctum::actingAs($user);

        $types = [
            'guest', 'delivery', 'cab', 'service', 'maid', 'driver',
            'cook', 'electrician', 'plumber', 'emergency', 'other',
        ];

        foreach ($types as $type) {
            $response = $this->postJson('/api/v1/visitors', [
                'visitor_name'  => 'Visitor ' . $type,
                'visitor_phone' => '9876543210',
                'visitor_type'  => $type,
                'flat_id'       => $flat->id,
            ]);

            $response->assertStatus(201, "visitor_type '{$type}' was rejected by the database");
            $this->assertSame($type, $response->json('data.visitor_type'));
        }

        $this->assertDatabaseCount('visitors', count($types));
    }

    /** A type outside the allowed set must be a 422, never a 500. */
    public function test_unknown_visitor_type_is_a_validation_error(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/visitors', [
            'visitor_name'  => 'Nobody',
            'visitor_phone' => '9876543210',
            'visitor_type'  => 'spaceship',
            'flat_id'       => $flat->id,
        ])->assertStatus(422);
    }

    /**
     * ServiceApiController::requestService inserts into service_requests,
     * which had no migration at all.
     */
    public function test_service_request_table_exists_and_accepts_a_request(): void
    {
        $this->assertTrue(
            Schema::hasTable('service_requests'),
            'service_requests table is missing; ServiceApiController::requestService writes to it'
        );

        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        $service = Service::create([
            'name'     => 'Plumbing',
            'category' => 'Home',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/service-requests', [
            'service_id'  => $service->id,
            'description' => 'Kitchen sink is blocked',
        ])->assertStatus(201);

        $this->assertDatabaseHas('service_requests', [
            'user_id'    => $user->id,
            'service_id' => $service->id,
            'status'     => 'pending',
        ]);
    }

    /**
     * A complaint with no matching category must persist, not 500.
     * complaints.complaint_category_id was NOT NULL.
     */
    public function test_complaint_without_a_category_can_be_created(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/complaints', [
            'title'       => 'No category',
            'description' => 'Nothing matches this text',
            'category'    => 'zzz-nonexistent-category',
        ])->assertStatus(201);

        $this->assertDatabaseHas('complaints', [
            'title'                 => 'No category',
            'complaint_category_id' => null,
        ]);
    }

    /**
     * Columns that dashboards query must exist. `visit_date` and `paid_at`
     * were referenced across six dashboard queries and exist on neither
     * table — silently returning zero on SQLite, fatal on MySQL.
     */
    public function test_columns_referenced_by_dashboards_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('visitors', 'expected_entry_time'));
        $this->assertTrue(Schema::hasColumn('payments', 'payment_date'));

        $this->assertFalse(
            Schema::hasColumn('visitors', 'visit_date'),
            'visitors.visit_date now exists - dashboard queries should use it directly'
        );
        $this->assertFalse(
            Schema::hasColumn('payments', 'paid_at'),
            'payments.paid_at now exists - dashboard queries should use it directly'
        );
    }

    /** The visitors table must carry the indexes its hot queries rely on. */
    public function test_visitors_table_has_its_lookup_indexes(): void
    {
        $indexes = $this->indexColumnsFor('visitors');

        $this->assertContains('host_user_id,approval_status', $indexes);
        $this->assertContains('expected_entry_time,entry_status', $indexes);
        $this->assertContains('flat_id,expected_entry_time', $indexes);
    }

    /** @return array<int,string> comma-joined column lists, one per index */
    private function indexColumnsFor(string $table): array
    {
        $connection = Schema::getConnection();
        $result     = [];

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select("PRAGMA index_list('{$table}')") as $index) {
                $columns = collect($connection->select("PRAGMA index_info('{$index->name}')"))
                    ->pluck('name')->implode(',');
                $result[] = $columns;
            }

            return $result;
        }

        $grouped = [];
        foreach ($connection->select("SHOW INDEX FROM `{$table}`") as $row) {
            $grouped[$row->Key_name][$row->Seq_in_index] = $row->Column_name;
        }
        foreach ($grouped as $columns) {
            ksort($columns);
            $result[] = implode(',', $columns);
        }

        return $result;
    }
}
