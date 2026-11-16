<?php

declare(strict_types=1);

namespace App\Services\Comex\Support;

final class Tolerance
{
    /**
     * Relative percent difference: |a-b| / max(|a|,|b|,eps) * 100.
     */
    public static function percentDelta(?float $a, ?float $b): ?float
    {
        if ($a === null || $b === null) {
            return null;
        }

        $denominator = max(abs($a), abs($b), 0.000001);

        return (abs($a - $b) / $denominator) * 100.0;
    }

    public static function exceeds(?float $a, ?float $b, float $tolerancePct): bool
    {
        $delta = self::percentDelta($a, $b);

        if ($delta === null) {
            return false;
        }

        return $delta > $tolerancePct;
    }
}
