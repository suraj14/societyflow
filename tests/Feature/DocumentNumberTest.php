<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\MaintenanceBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Document numbers were generated as `->count() + 1`.
 *
 * complaints.complaint_number, maintenance_bills.bill_number and
 * facility_bookings.booking_number all carry UNIQUE indexes, so this breaks
 * as soon as anything is deleted: the count drops and the next document
 * reuses a number that already exists. It does not need concurrency - one
 * deletion is enough to make the next create fail outright.
 *
 * The invariant these tests hold the generator to is that no two LIVE
 * documents ever share a number. Deriving the sequence from the highest
 * number already issued does guarantee that.
 *
 * It does not guarantee that a number is never reissued: delete the most
 * recent document and the next one takes its number back, because nothing
 * durable records the high-water mark. That is a smaller problem than a
 * failed insert, but it is a real one for bills - a number already sent to
 * a resident can reappear on a different bill. Closing it properly needs a
 * per-society counter table, which is noted as follow-up work rather than
 * added here.
 */
class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    private function makeComplaint(int $societyId, int $categoryId, int $flatId, int $userId): Complaint
    {
        return Complaint::create([
            'society_id'            => $societyId,
            'complaint_category_id' => $categoryId,
            'flat_id'               => $flatId,
            'created_by'            => $userId,
            'title'                 => 'Test',
            'description'           => 'Test',
            'status'                => 'open',
            'priority'              => 'medium',
        ]);
    }

    public function test_complaint_numbers_survive_a_deletion(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->makeResidentUser($society, $flat);
        $category = ComplaintCategory::create([
            'society_id' => $society->id, 'name' => 'General', 'status' => 'active',
        ]);

        $first  = $this->makeComplaint($society->id, $category->id, $flat->id, $user->id);
        $second = $this->makeComplaint($society->id, $category->id, $flat->id, $user->id);
        $third  = $this->makeComplaint($society->id, $category->id, $flat->id, $user->id);

        $this->assertNotSame($first->complaint_number, $second->complaint_number);

        // Delete from the middle. Under count()+1 the next create would
        // regenerate $third's number and die on the unique index.
        $second->delete();

        $fourth = $this->makeComplaint($society->id, $category->id, $flat->id, $user->id);

        $live = Complaint::pluck('complaint_number')->all();
        $this->assertSame(count($live), count(array_unique($live)));
        $this->assertNotSame($first->complaint_number, $fourth->complaint_number);
        $this->assertNotSame($third->complaint_number, $fourth->complaint_number);
    }

    public function test_many_complaints_all_get_distinct_numbers(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $user     = $this->makeResidentUser($society, $flat);
        $category = ComplaintCategory::create([
            'society_id' => $society->id, 'name' => 'General', 'status' => 'active',
        ]);

        $numbers = [];
        for ($i = 0; $i < 12; $i++) {
            $numbers[] = $this->makeComplaint($society->id, $category->id, $flat->id, $user->id)->complaint_number;
        }

        // Delete a few from the middle, then create more.
        Complaint::whereIn('complaint_number', array_slice($numbers, 3, 4))->delete();

        for ($i = 0; $i < 6; $i++) {
            $numbers[] = $this->makeComplaint($society->id, $category->id, $flat->id, $user->id)->complaint_number;
        }

        $live = Complaint::pluck('complaint_number')->all();
        $this->assertSame(count($live), count(array_unique($live)), 'duplicate complaint numbers were issued');
    }

    public function test_bill_numbers_survive_a_deletion(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        $make = fn (string $month) => MaintenanceBill::create([
            'society_id'     => $society->id,
            'flat_id'        => $flat->id,
            'month'          => $month,
            'year'           => 2026,
            'bill_date'      => now()->toDateString(),
            'due_date'       => now()->addDays(10)->toDateString(),
            'total_amount'   => 1000,
            'balance_amount' => 1000,
            'status'         => 'pending',
        ]);

        $first  = $make('January');
        $second = $make('February');
        $third  = $make('March');

        $second->delete();

        $fourth = $make('April');

        $live = MaintenanceBill::pluck('bill_number')->all();
        $this->assertSame(count($live), count(array_unique($live)), 'duplicate bill numbers were issued');
        $this->assertNotSame($first->bill_number, $fourth->bill_number);
        $this->assertNotSame($third->bill_number, $fourth->bill_number);
    }

    public function test_numbers_are_scoped_per_society(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $flatA    = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $flatB    = $this->makeFlat($societyB, $this->makeBuilding($societyB));
        $userA    = $this->makeResidentUser($societyA, $flatA);
        $userB    = $this->makeResidentUser($societyB, $flatB);

        $catA = ComplaintCategory::create(['society_id' => $societyA->id, 'name' => 'G', 'status' => 'active']);
        $catB = ComplaintCategory::create(['society_id' => $societyB->id, 'name' => 'G', 'status' => 'active']);

        $a = $this->makeComplaint($societyA->id, $catA->id, $flatA->id, $userA->id);
        $b = $this->makeComplaint($societyB->id, $catB->id, $flatB->id, $userB->id);

        $this->assertStringContainsString(str_pad((string) $societyA->id, 3, '0', STR_PAD_LEFT), $a->complaint_number);
        $this->assertStringContainsString(str_pad((string) $societyB->id, 3, '0', STR_PAD_LEFT), $b->complaint_number);
        $this->assertNotSame($a->complaint_number, $b->complaint_number);
    }
}
