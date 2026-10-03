<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The one period a report is about - monthly (month + year) or annual (year).
 *
 * Every report entry point (web page, PDF download, both report types)
 * resolves its period through fromRequest() so that the queried period, the
 * on-screen label, the PDF label and the filename can never disagree. A
 * month or year that is present but invalid is rejected with a validation
 * error, never silently replaced with "now" - that silent fallback is how
 * "June 2025" once rendered as the current month.
 */
final class ReportPeriod
{
    public const MONTHLY  = 'monthly';
    public const ANNUALLY = 'annually';

    private function __construct(
        public readonly string $type,
        public readonly int $year,
        public readonly ?int $month,
    ) {
    }

    public static function monthly(int $year, int $month): self
    {
        return new self(self::MONTHLY, $year, $month);
    }

    public static function annually(int $year): self
    {
        return new self(self::ANNUALLY, $year, null);
    }

    /** The current month on the signed-in user's society calendar. */
    public static function current(): self
    {
        $now = \App\Support\SocietyClock::now();

        return self::monthly($now->year, $now->month);
    }

    /**
     * Resolve from request input. Absent fields default to the current
     * month/year; present-but-invalid fields throw ValidationException.
     *
     * @throws ValidationException
     */
    public static function fromRequest(Request $request): self
    {
        $input = [
            'report_type' => $request->input('report_type', self::MONTHLY),
            'month'       => $request->input('month'),
            'year'        => $request->input('year'),
        ];

        // Treat empty strings (an unselected <select>) as absent.
        foreach (['month', 'year'] as $key) {
            if ($input[$key] === '' || $input[$key] === null) {
                unset($input[$key]);
            }
        }

        $validator = Validator::make($input, [
            'report_type' => 'required|in:' . self::MONTHLY . ',' . self::ANNUALLY,
            'month'       => 'sometimes|integer|between:1,12',
            'year'        => 'sometimes|integer|between:2000,2100',
        ], [
            'report_type.in' => 'Report type must be Monthly or Annual.',
            'month.between'  => 'The selected month is not valid.',
            'year.between'   => 'The selected year is not valid.',
        ]);

        $data = $validator->validate();

        // Absent fields default to "now" on the society's own calendar, not
        // the storage clock (UTC is still last month for 5½ hours on the 1st).
        $now  = \App\Support\SocietyClock::now();
        $year = (int) ($data['year'] ?? $now->year);

        if ($data['report_type'] === self::ANNUALLY) {
            return self::annually($year);
        }

        return self::monthly($year, (int) ($data['month'] ?? $now->month));
    }

    public function isMonthly(): bool
    {
        return $this->type === self::MONTHLY;
    }

    public function start(): Carbon
    {
        return $this->isMonthly()
            ? Carbon::create($this->year, $this->month, 1)->startOfMonth()
            : Carbon::create($this->year, 1, 1)->startOfYear();
    }

    public function end(): Carbon
    {
        return $this->isMonthly()
            ? Carbon::create($this->year, $this->month, 1)->endOfMonth()
            : Carbon::create($this->year, 1, 1)->endOfYear();
    }

    /** "June 2025" or "Year 2025". */
    public function label(): string
    {
        return $this->isMonthly()
            ? $this->start()->format('F Y')
            : 'Year ' . $this->year;
    }

    /** Safe for a filename: "June-2025" / "2025". */
    public function slug(): string
    {
        return $this->isMonthly()
            ? $this->start()->format('F-Y')
            : (string) $this->year;
    }

    /** @return array{type:string,year:int,month:int|null,label:string,start:string,end:string} */
    public function toArray(): array
    {
        return [
            'type'  => $this->type,
            'year'  => $this->year,
            'month' => $this->month,
            'label' => $this->label(),
            'start' => $this->start()->toDateString(),
            'end'   => $this->end()->toDateString(),
        ];
    }
}
