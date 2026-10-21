<?php

namespace App\Console\Commands;

use App\Models\AudioSegment;
use App\Models\Organization;
use App\Models\UsageLog;
use App\Services\Cost\CostCalculator;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateStorageCostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:calculate-cost {--date= : Tanggal perhitungan biaya (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghitung estimasi biaya penyimpanan berkas audio aktif per lembaga secara harian berdasarkan tarif per GB-bulan.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateParam = $this->option('date');
        $targetDate = $dateParam ? Carbon::parse($dateParam) : Carbon::today();
        $dateStr = $targetDate->toDateString();

        $this->info("Menghitung biaya penyimpanan audio untuk tanggal: {$dateStr}");

        $organizations = Organization::all();
        $countProcessed = 0;

        foreach ($organizations as $org) {
            // Calculate total bytes of active audio segments for meetings where audio has not been purged
            $totalBytes = (int) AudioSegment::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->whereHas('meeting', function ($q) {
                    $q->withoutGlobalScopes()->whereNull('audio_deleted_at');
                })
                ->sum('size_bytes');

            $disk = (string) config('recording.disk', 'local');
            $provider = ($disk === 'local') ? 'local_disk' : $disk;
            $model = 'audio_storage';
            $unit = 'gb_bulan';

            // Resolve rate snapshot for gb_bulan
            $costInfo = CostCalculator::calculate($provider, $model, $unit, 1.0, $targetDate);
            $rateSnapshot = (float) $costInfo['rate_snapshot'];
            $fxRateUsed = (float) $costInfo['fx_rate_used'];

            // Convert bytes to GB and compute 1-day portion of monthly rate (1/30)
            $totalGb = $totalBytes / (1024 * 1024 * 1024);
            $dailyCostUsd = round($totalGb * ($rateSnapshot / 30.0), 6);
            $dailyCostIdr = round($dailyCostUsd * $fxRateUsed, 2);

            // Idempotent record per organization per date
            $existingLog = UsageLog::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('kind', 'penyimpanan')
                ->whereDate('created_at', $dateStr)
                ->first();

            $logPayload = [
                'organization_id' => $org->id,
                'meeting_id' => null,
                'user_id' => null,
                'kind' => 'penyimpanan',
                'provider' => $provider,
                'model' => $model,
                'unit' => 'byte_hari',
                'qty' => $totalBytes,
                'audio_sec' => 0,
                'rate_snapshot' => $rateSnapshot,
                'est_cost_usd' => $dailyCostUsd,
                'est_cost_idr' => $dailyCostIdr,
                'fx_rate_used' => $fxRateUsed,
                'est_cost' => $dailyCostUsd,
                'status' => 'sukses',
                'attempt' => 1,
                'http_status' => 200,
                'created_at' => $targetDate->copy()->setTime(1, 0, 0),
            ];

            if ($existingLog) {
                $existingLog->update($logPayload);
            } else {
                UsageLog::withoutGlobalScopes()->create($logPayload);
            }

            Audit::record('storage_cost_calculated', null, [
                'organization_id' => $org->id,
                'date' => $dateStr,
                'total_bytes' => $totalBytes,
                'total_gb' => round($totalGb, 4),
                'cost_idr' => $dailyCostIdr,
            ]);

            $this->line("• Org: {$org->name} | {$totalBytes} bytes | Rp ".number_format($dailyCostIdr, 2));
            $countProcessed++;
        }

        $this->info("Selesai. {$countProcessed} organisasi diproses.");

        return self::SUCCESS;
    }
}
