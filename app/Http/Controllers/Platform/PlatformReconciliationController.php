<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRecord;
use App\Models\UsageLog;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PlatformReconciliationController extends Controller
{
    /**
     * Display monthly provider reconciliation comparison.
     */
    public function index(Request $request): View
    {
        $month = (string) $request->input('month', Carbon::now()->format('Y-m'));

        $logs = UsageLog::withoutGlobalScopes()
            ->where('created_at', 'like', "{$month}%")
            ->get();

        // Standard providers to reconcile
        $providerKeys = ['groq', 'openai', 'local_disk', 's3'];

        // Also discover any additional providers found in usage_logs
        $discoveredProviders = $logs->pluck('provider')->unique()->filter()->all();
        $allProviders = array_unique(array_merge($providerKeys, $discoveredProviders));

        $savedRecords = ReconciliationRecord::where('month', $month)->get()->keyBy('provider');

        $reconciliations = [];
        $totalEstUsdAll = 0.0;
        $totalEstIdrAll = 0.0;
        $totalActualUsdAll = 0.0;
        $totalActualIdrAll = 0.0;

        foreach ($allProviders as $provider) {
            $pLogs = $logs->where('provider', $provider);

            $estUsd = (float) $pLogs->sum('est_cost_usd');
            $estIdr = (float) $pLogs->sum('est_cost_idr');

            /** @var ReconciliationRecord|null $saved */
            $saved = $savedRecords->get($provider);

            $actualUsd = $saved?->actual_cost_usd;
            $actualIdr = $saved?->actual_cost_idr;

            $diffUsd = null;
            $diffIdr = null;
            $diffPct = null;

            if ($actualUsd !== null && $actualUsd > 0) {
                $diffUsd = round($estUsd - $actualUsd, 4);
                $diffPct = round((($estUsd - $actualUsd) / $actualUsd) * 100, 2);
            }

            if ($actualIdr !== null && $actualIdr > 0) {
                $diffIdr = round($estIdr - $actualIdr, 2);
                if ($diffPct === null) {
                    $diffPct = round((($estIdr - $actualIdr) / $actualIdr) * 100, 2);
                }
            }

            // Only show providers that either have logs or have a saved reconciliation record
            if ($pLogs->isNotEmpty() || $saved !== null) {
                $reconciliations[] = [
                    'provider' => $provider,
                    'est_usd' => $estUsd,
                    'est_idr' => $estIdr,
                    'actual_usd' => $actualUsd,
                    'actual_idr' => $actualIdr,
                    'diff_usd' => $diffUsd,
                    'diff_idr' => $diffIdr,
                    'diff_pct' => $diffPct,
                    'notes' => $saved?->notes,
                    'recorded_at' => $saved?->updated_at,
                ];

                $totalEstUsdAll += $estUsd;
                $totalEstIdrAll += $estIdr;
                $totalActualUsdAll += (float) ($actualUsd ?? 0.0);
                $totalActualIdrAll += (float) ($actualIdr ?? 0.0);
            }
        }

        return view('platform.reconciliation', [
            'month' => $month,
            'reconciliations' => $reconciliations,
            'totals' => [
                'est_usd' => $totalEstUsdAll,
                'est_idr' => $totalEstIdrAll,
                'actual_usd' => $totalActualUsdAll,
                'actual_idr' => $totalActualIdrAll,
                'diff_idr' => $totalActualIdrAll > 0 ? round($totalEstIdrAll - $totalActualIdrAll, 2) : null,
            ],
        ]);
    }

    /**
     * Save or update manual actual provider invoice cost.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'provider' => ['required', 'string', 'max:50'],
            'actual_cost_usd' => ['nullable', 'numeric', 'min:0'],
            'actual_cost_idr' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'month.required' => 'Bulan rekonsiliasi wajib ditentukan.',
            'provider.required' => 'Nama penyedia (provider) wajib dipilih.',
        ]);

        ReconciliationRecord::updateOrCreate(
            [
                'month' => $validated['month'],
                'provider' => $validated['provider'],
            ],
            [
                'actual_cost_usd' => $validated['actual_cost_usd'] !== null ? (float) $validated['actual_cost_usd'] : null,
                'actual_cost_idr' => $validated['actual_cost_idr'] !== null ? (float) $validated['actual_cost_idr'] : null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => auth('platform')->id(),
            ]
        );

        Audit::record('platform_reconciliation_save', null, [
            'month' => $validated['month'],
            'provider' => $validated['provider'],
            'actual_cost_usd' => $validated['actual_cost_usd'] ?? null,
            'actual_cost_idr' => $validated['actual_cost_idr'] ?? null,
            'ip' => $request->ip(),
        ]);

        return back()->with('success', "Data rekonsiliasi untuk provider '{$validated['provider']}' bulan {$validated['month']} berhasil disimpan.");
    }
}
