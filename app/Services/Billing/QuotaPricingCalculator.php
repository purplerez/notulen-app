<?php

namespace App\Services\Billing;

class QuotaPricingCalculator
{
    /**
     * Calculate quotation details for a given number of hours.
     *
     * @return array{
     *     hours: int,
     *     minutes: int,
     *     base_rate_per_hour: float,
     *     discount_pct: int,
     *     discount_amount_per_hour: float,
     *     effective_rate_per_hour: float,
     *     subtotal: float,
     *     total_discount: float,
     *     total_amount: float
     * }
     */
    public function calculate(int $hours): array
    {
        $minHours = (int) config('quota.min_hours', 1);
        $maxHours = (int) config('quota.max_hours', 100);
        $hours = max($minHours, min($maxHours, $hours));

        $baseRate = (float) config('quota.rate_per_hour_idr', 50000);
        $tiers = config('quota.tiers', []);

        $discountPct = 0;
        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_hours'] ?? 1);
            $max = isset($tier['max_hours']) ? (int) $tier['max_hours'] : null;

            if ($hours >= $min && ($max === null || $hours <= $max)) {
                $discountPct = (int) ($tier['discount_pct'] ?? 0);
                break;
            }
        }

        $discountPerHr = round($baseRate * ($discountPct / 100), 2);
        $effectiveRate = $baseRate - $discountPerHr;
        $subtotal = $hours * $baseRate;
        $totalDiscount = $hours * $discountPerHr;
        $totalAmount = $hours * $effectiveRate;

        return [
            'hours' => $hours,
            'minutes' => $hours * 60,
            'base_rate_per_hour' => $baseRate,
            'discount_pct' => $discountPct,
            'discount_amount_per_hour' => $discountPerHr,
            'effective_rate_per_hour' => $effectiveRate,
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'total_amount' => $totalAmount,
        ];
    }
}

