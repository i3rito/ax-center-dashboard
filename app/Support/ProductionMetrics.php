<?php

namespace App\Support;

final class ProductionMetrics
{
    public const DEFECT_ALERT_THRESHOLD = 5.0;

    public static function defectRate(int $produced, int $defective): float
    {
        if ($produced <= 0) {
            return 0.0;
        }

        return round(($defective / $produced) * 100, 2);
    }

    public static function efficiency(int $produced, int $defective): float
    {
        if ($produced <= 0) {
            return 0.0;
        }

        $good = max(0, $produced - $defective);

        return round(($good / $produced) * 100, 2);
    }

    public static function isAlert(float $defectRate): bool
    {
        return $defectRate > self::DEFECT_ALERT_THRESHOLD;
    }
}
