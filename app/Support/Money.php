<?php

namespace App\Support;

/**
 * Money is added in whole paise (integers), never as floating point rupees: 0.1 + 0.2 is not 0.3 as a float, and a report
 * built from many such additions can be a paisa out and then fail to reconcile with its own parts. Amounts come out of
 * the database as decimal strings ("2700.00") or floats; each is converted to paise exactly once, the paise are summed as
 * integers, and the total is turned back into rupees (two places) only for display.
 */
class Money
{
    /** "2700.00" / 2700 / 2700.5 / null -> 270000 / 270000 / 270050 / 0 */
    public static function paise(null|int|float|string $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    /** Rupees to two places from whole paise. */
    public static function rupees(int $paise): float
    {
        return round($paise / 100, 2);
    }

    /** Exact sum of many amounts, in rupees. @param iterable<null|int|float|string> $amounts */
    public static function sum(iterable $amounts): float
    {
        $total = 0;
        foreach ($amounts as $a) {
            $total += self::paise($a);
        }

        return self::rupees($total);
    }

    /** a - b, exactly, in rupees. */
    public static function diff(null|int|float|string $a, null|int|float|string $b): float
    {
        return self::rupees(self::paise($a) - self::paise($b));
    }

    /** Whether two amounts are the same to the paisa. */
    public static function same(null|int|float|string $a, null|int|float|string $b): bool
    {
        return self::paise($a) === self::paise($b);
    }
}
