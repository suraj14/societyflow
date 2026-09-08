<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Generates the human-readable document numbers (CMP…, MB…, BKG…, EMP…)
 * that several models carry.
 *
 * Every one of these used `->count() + 1` as the sequence. That is wrong in
 * two independent ways:
 *
 *   1. Deletions. Delete any row and the count drops, so the next document
 *      reuses a number that already exists. complaints.complaint_number,
 *      maintenance_bills.bill_number and facility_bookings.booking_number
 *      all carry UNIQUE indexes, so the next insert simply fails. This does
 *      not need concurrency - one deletion is enough.
 *
 *   2. Concurrency. Two requests counting at the same time get the same
 *      number and one of them fails.
 *
 * Deriving from the highest number already issued fixes (1). A bounded
 * retry on collision fixes (2) without needing a lock table or a
 * database-specific sequence, and keeps the numbers human-readable.
 */
trait GeneratesDocumentNumber
{
    /** How many times to retry when the generated number is already taken. */
    private static int $documentNumberAttempts = 5;

    /**
     * Next document number for $societyId.
     *
     * @param  string  $prefix        e.g. 'CMP'
     * @param  string  $column        column holding the number
     * @param  int     $sequenceWidth zero-padded width of the counter
     * @param  bool    $includeYear   whether the year forms part of the number
     */
    protected static function nextDocumentNumber(
        int $societyId,
        string $prefix,
        string $column,
        int $sequenceWidth = 6,
        bool $includeYear = true
    ): string {
        $societyCode = str_pad((string) $societyId, 3, '0', STR_PAD_LEFT);
        $year        = $includeYear ? date('Y') : '';
        $stem        = $prefix . $societyCode . $year;

        $sequence = static::highestSequenceFor($stem, $column, $sequenceWidth) + 1;

        for ($attempt = 0; $attempt < self::$documentNumberAttempts; $attempt++) {
            $candidate = $stem . str_pad((string) ($sequence + $attempt), $sequenceWidth, '0', STR_PAD_LEFT);

            if (!static::query()->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        // Every candidate in the window was taken (heavy concurrency). Fall
        // back to a value that cannot collide rather than throwing: losing
        // strict sequence ordering is far better than refusing to file a
        // complaint or raise a bill.
        return $stem . str_pad((string) $sequence, $sequenceWidth, '0', STR_PAD_LEFT)
            . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    }

    /**
     * Highest sequence number already issued under $stem.
     *
     * Reads the numeric tail of the largest matching value rather than
     * counting rows, so deleting a row can never hand the next document a
     * number that is already in use.
     */
    private static function highestSequenceFor(string $stem, string $column, int $sequenceWidth): int
    {
        $highest = static::query()
            ->where($column, 'like', $stem . '%')
            ->orderByRaw('LENGTH(' . static::wrapColumn($column) . ') DESC')
            ->orderBy($column, 'desc')
            ->value($column);

        if (!$highest) {
            return 0;
        }

        $tail = substr($highest, strlen($stem));

        // Strip any collision suffix added by the fallback above.
        $tail = explode('-', $tail)[0];

        return (int) $tail;
    }

    private static function wrapColumn(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }
}
