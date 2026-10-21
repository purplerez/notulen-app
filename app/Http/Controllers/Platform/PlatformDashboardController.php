<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\UsageLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformDashboardController extends Controller
{
    /**
     * Display the main platform super admin overview dashboard.
     */
    public function index(Request $request): View
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $prevMonth = Carbon::now()->subMonth()->format('Y-m');

        $organizations = Organization::all();

        $orgMetrics = [];
        $totalCostMonthIdr = 0.0;
        $totalCostMonthUsd = 0.0;
        $totalCostPrevMonthIdr = 0.0;
        $totalAudioSecGlobal = 0.0;
        $totalMeetingsGlobal = 0;

        foreach ($organizations as $org) {
            $baseLogs = UsageLog::withoutGlobalScopes()->where('organization_id', $org->id);

            // Month logs
            $currentMonthLogs = (clone $baseLogs)->where('created_at', 'like', "{$currentMonth}%")->get();
            $prevMonthLogs = (clone $baseLogs)->where('created_at', 'like', "{$prevMonth}%")->get();
            $allLogs = (clone $baseLogs)->get();

            $costMonthIdr = (float) $currentMonthLogs->sum('est_cost_idr');
            $costMonthUsd = (float) $currentMonthLogs->sum('est_cost_usd');
            $costPrevMonthIdr = (float) $prevMonthLogs->sum('est_cost_idr');
            $costTotalIdr = (float) $allLogs->sum('est_cost_idr');

            // Breakdown by kind for current month
            $sttCostMonth = (float) $currentMonthLogs->where('kind', 'stt')->sum('est_cost_idr');
            $llmCostMonth = (float) $currentMonthLogs->where('kind', 'llm')->sum('est_cost_idr');
            $storageCostMonth = (float) $currentMonthLogs->where('kind', 'penyimpanan')->sum('est_cost_idr');

            // Audio hours & meetings count
            $audioSecTotal = (float) $allLogs->where('kind', 'stt')->sum('audio_sec');
            $audioHoursTotal = round($audioSecTotal / 3600.0, 2);

            $meetingsCount = Meeting::withoutGlobalScopes()->where('organization_id', $org->id)->count();
            $avgCostPerMeeting = $meetingsCount > 0 ? round($costTotalIdr / $meetingsCount, 2) : 0.0;

            // Quota minutes used (all-time or monthly quota)
            $usedMinutes = round($audioSecTotal / 60.0, 1);
            $quotaMinutes = (int) $org->quota_minutes;
            $quotaPct = $quotaMinutes > 0 ? min(100, round(($usedMinutes / $quotaMinutes) * 100, 1)) : 0;

            // Monthly trend percentage
            $trendPct = 0.0;
            if ($costPrevMonthIdr > 0) {
                $trendPct = round((($costMonthIdr - $costPrevMonthIdr) / $costPrevMonthIdr) * 100, 1);
            } elseif ($costMonthIdr > 0) {
                $trendPct = 100.0;
            }

            $orgMetrics[] = [
                'org' => $org,
                'cost_month_idr' => $costMonthIdr,
                'cost_month_usd' => $costMonthUsd,
                'cost_prev_month_idr' => $costPrevMonthIdr,
                'cost_total_idr' => $costTotalIdr,
                'stt_cost_month' => $sttCostMonth,
                'llm_cost_month' => $llmCostMonth,
                'storage_cost_month' => $storageCostMonth,
                'audio_hours_total' => $audioHoursTotal,
                'meetings_count' => $meetingsCount,
                'avg_cost_per_meeting' => $avgCostPerMeeting,
                'used_minutes' => $usedMinutes,
                'quota_minutes' => $quotaMinutes,
                'quota_pct' => $quotaPct,
                'trend_pct' => $trendPct,
            ];

            $totalCostMonthIdr += $costMonthIdr;
            $totalCostMonthUsd += $costMonthUsd;
            $totalCostPrevMonthIdr += $costPrevMonthIdr;
            $totalAudioSecGlobal += $audioSecTotal;
            $totalMeetingsGlobal += $meetingsCount;
        }

        Audit::record('platform_dashboard_view', null, [
            'ip' => $request->ip(),
        ]);

        return view('platform.dashboard', [
            'orgMetrics' => $orgMetrics,
            'summary' => [
                'total_cost_month_idr' => $totalCostMonthIdr,
                'total_cost_month_usd' => $totalCostMonthUsd,
                'total_cost_prev_month_idr' => $totalCostPrevMonthIdr,
                'total_audio_hours' => round($totalAudioSecGlobal / 3600.0, 1),
                'total_meetings' => $totalMeetingsGlobal,
                'total_orgs' => $organizations->count(),
            ],
            'currentMonth' => $currentMonth,
        ]);
    }

    /**
     * Display detailed cost breakdown for a single organization.
     * STRICT PRIVACY: Meeting titles, attendees, transcripts, audio, and minutes contents are OMITTED.
     */
    public function organization(Request $request, Organization $organization): View
    {
        $month = (string) $request->input('month', Carbon::now()->format('Y-m'));

        $queryLogs = UsageLog::withoutGlobalScopes()
            ->where('organization_id', $organization->id);

        if ($month) {
            $queryLogs->where('created_at', 'like', "{$month}%");
        }

        $logs = $queryLogs->get();

        $costPeriodIdr = (float) $logs->sum('est_cost_idr');
        $costPeriodUsd = (float) $logs->sum('est_cost_usd');
        $sttCost = (float) $logs->where('kind', 'stt')->sum('est_cost_idr');
        $llmCost = (float) $logs->where('kind', 'llm')->sum('est_cost_idr');
        $storageCost = (float) $logs->where('kind', 'penyimpanan')->sum('est_cost_idr');
        $totalAudioSec = (float) $logs->where('kind', 'stt')->sum('audio_sec');

        // 1. Daily breakdown for the selected month
        $dailyBreakdown = [];
        $groupedDaily = $logs->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        foreach ($groupedDaily as $dayStr => $dayLogs) {
            $dailyBreakdown[] = [
                'date' => $dayStr,
                'stt_idr' => (float) $dayLogs->where('kind', 'stt')->sum('est_cost_idr'),
                'llm_idr' => (float) $dayLogs->where('kind', 'llm')->sum('est_cost_idr'),
                'storage_idr' => (float) $dayLogs->where('kind', 'penyimpanan')->sum('est_cost_idr'),
                'total_idr' => (float) $dayLogs->sum('est_cost_idr'),
            ];
        }

        usort($dailyBreakdown, fn ($a, $b) => strcmp($b['date'], $a['date']));

        // 2. Breakdown per Notulis (Creator)
        $notulisUsers = User::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('role', 'notulis')
            ->get();

        $notulisBreakdown = [];
        foreach ($notulisUsers as $notulis) {
            $notulisLogs = $logs->where('user_id', $notulis->id);
            $notulisMeetingsCount = Meeting::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('created_by', $notulis->id)
                ->when($month, fn ($q) => $q->where('meeting_date', 'like', "{$month}%"))
                ->count();

            $notulisBreakdown[] = [
                'user' => $notulis,
                'meetings_count' => $notulisMeetingsCount,
                'audio_hours' => round((float) $notulisLogs->where('kind', 'stt')->sum('audio_sec') / 3600.0, 2),
                'cost_idr' => (float) $notulisLogs->sum('est_cost_idr'),
            ];
        }

        // 3. Breakdown per Meeting (ID Rapat, Tanggal, Durasi, Notulis, Biaya per jenis, Percobaan Gagal)
        // NOTICE: Titles and content are strictly EXCLUDED for privacy.
        $meetingsQuery = Meeting::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with(['creator']);

        if ($month) {
            $meetingsQuery->where('meeting_date', 'like', "{$month}%");
        }

        $meetings = $meetingsQuery->orderBy('meeting_date', 'desc')->get();
        $meetingBreakdown = [];

        foreach ($meetings as $meeting) {
            $mLogs = $logs->where('meeting_id', $meeting->id);

            $mSttCost = (float) $mLogs->where('kind', 'stt')->sum('est_cost_idr');
            $mLlmCost = (float) $mLogs->where('kind', 'llm')->sum('est_cost_idr');
            $mStorageCost = (float) $mLogs->where('kind', 'penyimpanan')->sum('est_cost_idr');
            $mTotalCost = (float) $mLogs->sum('est_cost_idr');

            $mFailedAttempts = $mLogs->where('status', 'gagal')->count();
            $mAudioDuration = (float) $mLogs->where('kind', 'stt')->sum('audio_sec');

            $meetingBreakdown[] = [
                'id' => $meeting->id,
                'date' => $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->format('d M Y') : $meeting->created_at->format('d M Y'),
                'creator_name' => $meeting->creator->name ?? 'Notulis #'.$meeting->created_by,
                'duration_sec' => $mAudioDuration,
                'duration_minutes' => round($mAudioDuration / 60.0, 1),
                'stt_cost_idr' => $mSttCost,
                'llm_cost_idr' => $mLlmCost,
                'storage_cost_idr' => $mStorageCost,
                'total_cost_idr' => $mTotalCost,
                'failed_attempts' => $mFailedAttempts,
            ];
        }

        Audit::record('platform_org_view', null, [
            'organization_id' => $organization->id,
            'month' => $month,
            'ip' => $request->ip(),
        ]);

        return view('platform.organization_detail', [
            'organization' => $organization,
            'month' => $month,
            'costPeriodIdr' => $costPeriodIdr,
            'costPeriodUsd' => $costPeriodUsd,
            'sttCost' => $sttCost,
            'llmCost' => $llmCost,
            'storageCost' => $storageCost,
            'totalAudioHours' => round($totalAudioSec / 3600.0, 2),
            'dailyBreakdown' => $dailyBreakdown,
            'notulisBreakdown' => $notulisBreakdown,
            'meetingBreakdown' => $meetingBreakdown,
        ]);
    }

    /**
     * Export cost records for an organization to CSV.
     * STRICT PRIVACY: Meeting titles, attendees, and transcripts are omitted.
     */
    public function exportCsv(Request $request, Organization $organization): StreamedResponse
    {
        $month = (string) $request->input('month', Carbon::now()->format('Y-m'));

        Audit::record('platform_export_csv', null, [
            'organization_id' => $organization->id,
            'month' => $month,
            'ip' => $request->ip(),
        ]);

        $meetingsQuery = Meeting::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with(['creator']);

        if ($month) {
            $meetingsQuery->where('meeting_date', 'like', "{$month}%");
        }

        $meetings = $meetingsQuery->orderBy('meeting_date', 'desc')->get();

        $filename = "biaya-org-{$organization->slug}-{$month}.csv";

        return response()->streamDownload(function () use ($meetings, $organization, $month) {
            $handle = fopen('php://output', 'w');

            // Header line
            fputcsv($handle, [
                'ID Rapat',
                'Tanggal',
                'Nama Notulis',
                'Durasi (Detik)',
                'Durasi (Menit)',
                'Biaya STT (IDR)',
                'Biaya LLM (IDR)',
                'Biaya Penyimpanan (IDR)',
                'Total Biaya (IDR)',
                'Percobaan Gagal',
            ]);

            $logs = UsageLog::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->when($month, fn ($q) => $q->where('created_at', 'like', "{$month}%"))
                ->get();

            foreach ($meetings as $meeting) {
                $mLogs = $logs->where('meeting_id', $meeting->id);

                $mSttCost = (float) $mLogs->where('kind', 'stt')->sum('est_cost_idr');
                $mLlmCost = (float) $mLogs->where('kind', 'llm')->sum('est_cost_idr');
                $mStorageCost = (float) $mLogs->where('kind', 'penyimpanan')->sum('est_cost_idr');
                $mTotalCost = (float) $mLogs->sum('est_cost_idr');

                $mFailedAttempts = $mLogs->where('status', 'gagal')->count();
                $mAudioDuration = (float) $mLogs->where('kind', 'stt')->sum('audio_sec');

                fputcsv($handle, [
                    $meeting->id,
                    $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->format('Y-m-d') : $meeting->created_at->format('Y-m-d'),
                    $meeting->creator->name ?? 'Notulis #'.$meeting->created_by,
                    $mAudioDuration,
                    round($mAudioDuration / 60.0, 1),
                    $mSttCost,
                    $mLlmCost,
                    $mStorageCost,
                    $mTotalCost,
                    $mFailedAttempts,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
