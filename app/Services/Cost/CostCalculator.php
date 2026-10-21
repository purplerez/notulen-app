<?php

namespace App\Services\Cost;

use App\Models\FxRate;
use App\Models\PriceRate;
use Illuminate\Support\Carbon;

class CostCalculator
{
    /**
     * Calculate cost estimates (USD and IDR) based on rates active at the given timestamp.
     *
     * @return array{
     *     rate_snapshot: float,
     *     fx_rate_used: float,
     *     est_cost_usd: float,
     *     est_cost_idr: float
     * }
     */
    public static function calculate(
        string $provider,
        string $model,
        string $unit,
        float $qty,
        ?Carbon $at = null
    ): array {
        $at = $at ? $at->copy() : Carbon::now();

        // 1. Resolve active price rate snapshot
        /** @var PriceRate|null $activeRate */
        $activeRate = PriceRate::query()
            ->where('provider', $provider)
            ->where('model', $model)
            ->where('unit', $unit)
            ->where('effective_from', '<=', $at)
            ->orderBy('effective_from', 'desc')
            ->first();

        $rateSnapshot = 0.0;

        if ($activeRate) {
            $rateSnapshot = (float) $activeRate->price_usd;
        } else {
            // Check fallback defaults from config
            $defaults = config('pricing.defaults', []);
            foreach ($defaults as $default) {
                if (($default['provider'] ?? '') === $provider
                    && ($default['model'] ?? '') === $model
                    && ($default['unit'] ?? '') === $unit) {
                    $rateSnapshot = (float) ($default['price_usd'] ?? 0.0);
                    break;
                }
            }
        }

        // 2. Resolve active FX rate
        /** @var FxRate|null $activeFx */
        $activeFx = FxRate::query()
            ->whereDate('date', '<=', $at->toDateString())
            ->orderBy('date', 'desc')
            ->first();

        $fxRateUsed = $activeFx ? (float) $activeFx->usd_idr : (float) config('pricing.default_fx_rate', 16000.00);

        // 3. Compute cost
        $estCostUsd = $qty * $rateSnapshot;
        $estCostIdr = $estCostUsd * $fxRateUsed;

        return [
            'rate_snapshot' => $rateSnapshot,
            'fx_rate_used' => $fxRateUsed,
            'est_cost_usd' => round($estCostUsd, 6),
            'est_cost_idr' => round($estCostIdr, 2),
        ];
    }
}
