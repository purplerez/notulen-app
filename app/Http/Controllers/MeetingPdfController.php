<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Minute;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeetingPdfController extends Controller
{
    /**
     * Generate and stream/download the meeting minutes PDF document.
     */
    public function minute(Request $request, Meeting $meeting, ?Minute $minute = null): Response
    {
        $user = $request->user();

        // If minute is not provided in URL, determine from query param ?v= or defaults
        if (! $minute) {
            $versionParam = $request->query('v');
            if ($versionParam) {
                $minute = $meeting->minutes()->where('version', (int) $versionParam)->first();
            } else {
                if ($user && $user->isPimpinan()) {
                    $minute = $meeting->latestFinalMinute;
                } else {
                    $minute = $meeting->latestFinalMinute ?: $meeting->currentMinute;
                }
            }
        }

        if (! $minute || (int) $minute->meeting_id !== (int) $meeting->id) {
            abort(404, 'Notulen tidak ditemukan untuk rapat ini.');
        }

        Gate::authorize('view', $minute);

        $minute->load(['actionItems']);
        $meeting->load(['creator', 'attendees', 'organization']);

        $kopBase64 = $this->resolveKopBase64($meeting);
        $signatories = $meeting->signatories()
            ->orderBy('meeting_signatories.sort')
            ->orderBy('organization_signatories.sort')
            ->get();

        Audit::record('minute_pdf_export', $meeting->id, [
            'minute_id' => $minute->id,
            'version' => $minute->version,
            'is_final' => $minute->isFinal(),
        ]);

        $pdf = Pdf::loadView('pdf.minute', [
            'meeting' => $meeting,
            'minute' => $minute,
            'organization' => $meeting->organization,
            'signatories' => $signatories,
            'kopBase64' => $kopBase64,
        ])->setPaper('a4', 'portrait');

        $slugTitle = Str::slug($meeting->title) ?: "meeting-{$meeting->id}";
        $filename = "notulen-{$slugTitle}-v{$minute->version}.pdf";

        if ($request->query('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Generate and stream/download the attendance recap (Berita Acara) PDF document.
     */
    public function attendanceRecap(Request $request, Meeting $meeting): Response
    {
        Gate::authorize('view', $meeting);

        $meeting->load(['organization']);
        $attendees = $meeting->attendees()->orderBy('name')->get();

        $summary = [
            'total' => $attendees->count(),
            'hadir' => $attendees->where('status', 'hadir')->count(),
            'terlambat' => $attendees->where('status', 'terlambat')->count(),
            'izin' => $attendees->where('status', 'izin')->count(),
            'sakit' => $attendees->where('status', 'sakit')->count(),
            'dinas_luar' => $attendees->where('status', 'dinas_luar')->count(),
            'tanpa_keterangan' => $attendees->where('status', 'tanpa_keterangan')->count(),
            'belum_absen' => $attendees->whereNull('status')->count(),
        ];

        $totalPresent = $summary['hadir'] + $summary['terlambat'];
        $attendanceRate = $summary['total'] > 0 ? round(($totalPresent / $summary['total']) * 100, 1) : 0;

        $kopBase64 = $this->resolveKopBase64($meeting);
        $signatories = $meeting->signatories()
            ->orderBy('meeting_signatories.sort')
            ->orderBy('organization_signatories.sort')
            ->get();

        Audit::record('attendance_pdf_export', $meeting->id, [
            'total_attendees' => $summary['total'],
            'present_count' => $totalPresent,
        ]);

        $pdf = Pdf::loadView('pdf.attendance_recap', [
            'meeting' => $meeting,
            'attendees' => $attendees,
            'summary' => $summary,
            'attendanceRate' => $attendanceRate,
            'organization' => $meeting->organization,
            'signatories' => $signatories,
            'kopBase64' => $kopBase64,
        ])->setPaper('a4', 'portrait');

        $slugTitle = Str::slug($meeting->title) ?: "meeting-{$meeting->id}";
        $filename = "rekap-absensi-{$slugTitle}.pdf";

        if ($request->query('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Resolve organization kop image as Base64 Data URI if available in private storage.
     */
    protected function resolveKopBase64(Meeting $meeting): ?string
    {
        if (! $meeting->use_kop || ! $meeting->organization || ! $meeting->organization->kop_path) {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($meeting->organization->kop_path)) {
            return null;
        }

        $mime = $disk->mimeType($meeting->organization->kop_path) ?: 'image/png';
        $content = $disk->get($meeting->organization->kop_path);

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
