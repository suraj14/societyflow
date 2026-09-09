<?php

use App\Enums\SocietyPropertyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Adds societies.property_type - the single source of truth for whether a
 * society contains apartments, villas, or both.
 *
 * Backfill policy: infer only where the evidence is unambiguous, and never
 * choose a value that would hide existing rows. 'both' is the safe default
 * because it hides nothing; an administrator narrows it afterwards on the
 * society configuration screen.
 *
 *   apartments > 0 AND villas > 0                      -> both      (certain)
 *   villas > 0, no apartments, no buildings            -> villa     (certain)
 *   apartments > 0, no villas, no villa areas          -> apartment (certain)
 *   anything else (incl. empty societies, or a society
 *   with a villa area but no villas yet)               -> both + flagged
 *
 * Ambiguous societies are written to the log so they can be reviewed. No
 * existing column or row is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('societies', 'property_type')) {
            Schema::table('societies', function (Blueprint $table) {
                // Plain string rather than a DB enum: SQLite implements enums
                // as CHECK constraints, and widening one later needs a full
                // table rebuild (see 2026_09_07_000003_repair_visitors_table).
                // The value is constrained by App\Enums\SocietyPropertyType
                // and validated on every write.
                $table->string('property_type', 20)
                    ->default(SocietyPropertyType::Both->value)
                    ->after('description');

                $table->index('property_type');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (!Schema::hasColumn('societies', 'property_type')) {
            return;
        }

        Schema::table('societies', function (Blueprint $table) {
            $table->dropIndex(['property_type']);
            $table->dropColumn('property_type');
        });
    }

    private function backfill(): void
    {
        $ambiguous = [];

        $societies = DB::table('societies')->select('id', 'name')->get();

        foreach ($societies as $society) {
            $apartments = DB::table('flats')
                ->where('society_id', $society->id)
                ->where('property_type', 'apartment')
                ->count();

            $villas = DB::table('flats')
                ->where('society_id', $society->id)
                ->where('property_type', 'villa')
                ->count();

            $buildings = DB::table('buildings')->where('society_id', $society->id)->count();

            $villaAreas = Schema::hasTable('villa_areas')
                ? DB::table('villa_areas')->where('society_id', $society->id)->count()
                : 0;

            [$type, $certain] = $this->infer($apartments, $villas, $buildings, $villaAreas);

            DB::table('societies')->where('id', $society->id)->update(['property_type' => $type]);

            if (!$certain) {
                $ambiguous[] = sprintf(
                    '#%d %s (apartments=%d villas=%d buildings=%d villa_areas=%d) -> defaulted to "both"',
                    $society->id,
                    $society->name,
                    $apartments,
                    $villas,
                    $buildings,
                    $villaAreas
                );
            }
        }

        if ($ambiguous !== []) {
            Log::warning(
                'societies.property_type backfill: ' . count($ambiguous) . ' society/societies could not be '
                . 'determined from data and were set to "both" (hides nothing). Review them on the society '
                . 'configuration screen: ' . implode('; ', $ambiguous)
            );
        }
    }

    /**
     * @return array{0:string,1:bool} [property_type, certain]
     */
    private function infer(int $apartments, int $villas, int $buildings, int $villaAreas): array
    {
        if ($apartments > 0 && $villas > 0) {
            return [SocietyPropertyType::Both->value, true];
        }

        if ($villas > 0 && $apartments === 0 && $buildings === 0) {
            return [SocietyPropertyType::Villa->value, true];
        }

        if ($apartments > 0 && $villas === 0 && $villaAreas === 0) {
            return [SocietyPropertyType::Apartment->value, true];
        }

        // Empty society, or one holding a villa area with no villas yet -
        // narrowing here could hide a module the society is mid-way through
        // setting up.
        return [SocietyPropertyType::Both->value, false];
    }
};
