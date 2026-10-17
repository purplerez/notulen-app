<?php

namespace App\Http\Controllers;

use App\Events\MeetingAudioUploaded;
use App\Jobs\TranscribeSegment;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordingController extends Controller
{
    /**
     * Display the audio recording screen for the meeting.
     */
    public function show(Meeting $meeting): View
    {
        $this->authorize('record', $meeting);

        $org = auth()->user()->organization;

        return view('meetings.record', [
            'meeting' => $meeting,
            'organization' => $org,
            'maxDurationMinutes' => (int) config('recording.max_duration_minutes', 180),
            'retentionMonths' => (int) config('recording.retention_months', 1),
            'allowedMimes' => config('recording.allowed_mimes', []),
        ]);
    }

    /**
     * Start the recording session.
     */
    public function start(Meeting $meeting): JsonResponse
    {
        $this->authorize('record', $meeting);

        if (in_array($meeting->status, ['uploaded', 'transcribing', 'ready', 'failed'], true)) {
            return response()->json([
                'message' => 'Rapat ini sudah selesai direkam dan tidak dapat dimulai ulang.',
            ], 422);
        }

        $user = auth()->user();
        $org = $user?->organization;
        if (! ($org instanceof Organization) || ! $org->hasAvailableQuota()) {
            return response()->json([
                'message' => 'Kuota menit organisasi tidak mencukupi atau telah habis. Hubungi administrator.',
            ], 422);
        }

        if ($meeting->status !== 'recording') {
            $meeting->update([
                'status' => 'recording',
                'started_at' => now(),
            ]);

            Audit::record('recording_start', $meeting->id, [
                'title' => $meeting->title,
                'started_at' => $meeting->started_at ? Carbon::parse($meeting->started_at)->toIso8601String() : null,
            ]);
        }

        return response()->json([
            'status' => 'recording',
            'started_at' => $meeting->started_at ? Carbon::parse($meeting->started_at)->toIso8601String() : null,
            'max_duration_minutes' => (int) config('recording.max_duration_minutes', 180),
            'message' => 'Perekaman dimulai.',
        ]);
    }

    /**
     * Upload an audio segment (idempotent).
     */
    public function uploadSegment(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('record', $meeting);

        if (in_array($meeting->status, ['uploaded', 'transcribing', 'ready'], true)) {
            return response()->json([
                'message' => 'Rapat sudah selesai direkam, tidak dapat menerima segmen baru.',
            ], 422);
        }

        $maxKb = (int) config('recording.max_segment_size_kb', 25600);
        $validated = $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}"],
            'seq' => ['required', 'integer', 'min:1'],
            'checksum' => ['required', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
            'duration_sec' => ['nullable', 'numeric', 'min:0'],
        ]);

        $file = $request->file('file');
        $rawMime = $file->getClientMimeType() ?: $file->getMimeType();
        $baseMime = strtolower(trim(explode(';', $rawMime)[0]));

        $allowedMimes = array_map('strtolower', config('recording.allowed_mimes', []));
        $fileMime = strtolower($file->getMimeType() ?? '');

        if (! in_array($baseMime, $allowedMimes, true) && ! in_array($fileMime, $allowedMimes, true)) {
            return response()->json([
                'message' => "Tipe MIME audio tidak didukung: {$rawMime}",
            ], 422);
        }

        // Verify SHA-256 integrity
        $calculatedHash = hash_file('sha256', $file->getRealPath());
        if (strtolower($calculatedHash) !== strtolower($validated['checksum'])) {
            return response()->json([
                'message' => 'Verifikasi checksum gagal. Berkas audio korup atau tidak sesuai.',
            ], 422);
        }

        $seq = (int) $validated['seq'];
        $checksum = strtolower($validated['checksum']);

        // Check idempotency against existing segment
        $existing = AudioSegment::where('meeting_id', $meeting->id)
            ->where('seq', $seq)
            ->first();

        if ($existing) {
            if (strtolower($existing->checksum) === $checksum) {
                return response()->json([
                    'message' => 'Segmen audio sudah tersimpan.',
                    'segment' => $existing,
                    'idempotent' => true,
                ], 200);
            }

            return response()->json([
                'message' => "Segmen dengan seq {$seq} sudah ada dengan checksum berbeda.",
            ], 409);
        }

        // Determine file extension
        $ext = $file->getClientOriginalExtension() ?: match ($baseMime) {
            'audio/webm', 'video/webm' => 'webm',
            'audio/mp4', 'video/mp4' => 'mp4',
            'audio/aac' => 'aac',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/ogg', 'audio/opus' => 'ogg',
            default => 'bin',
        };

        $disk = config('recording.disk', 'local');
        $filename = "seg-{$seq}.{$ext}";
        $dir = "orgs/{$meeting->organization_id}/meetings/{$meeting->id}";
        $path = $file->storeAs($dir, $filename, $disk);

        try {
            $segment = AudioSegment::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'seq' => $seq,
                'path' => $path,
                'mime' => $rawMime,
                'size_bytes' => $file->getSize(),
                'duration_sec' => $validated['duration_sec'] ?? null,
                'checksum' => $checksum,
                'status' => 'uploaded',
            ]);

            return response()->json([
                'message' => 'Segmen audio berhasil disimpan.',
                'segment' => $segment,
            ], 201);
        } catch (UniqueConstraintViolationException) {
            // Race condition fallback
            $existing = AudioSegment::where('meeting_id', $meeting->id)->where('seq', $seq)->first();
            if ($existing && strtolower($existing->checksum) === $checksum) {
                return response()->json([
                    'message' => 'Segmen audio sudah tersimpan.',
                    'segment' => $existing,
                    'idempotent' => true,
                ], 200);
            }

            return response()->json([
                'message' => "Segmen dengan seq {$seq} konflik pada penyimpanan ganda.",
            ], 409);
        }
    }

    /**
     * Finish the recording session with expected segments verification.
     */
    public function finish(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('record', $meeting);

        $validated = $request->validate([
            'expected_segments' => ['required', 'integer', 'min:1'],
        ]);

        $expected = (int) $validated['expected_segments'];

        $storedSeqs = AudioSegment::where('meeting_id', $meeting->id)
            ->pluck('seq')
            ->map(fn ($s) => (int) $s)
            ->all();

        $requiredSeqs = range(1, $expected);
        $missing = array_values(array_diff($requiredSeqs, $storedSeqs));

        if (! empty($missing)) {
            return response()->json([
                'completed' => false,
                'message' => 'Segmen audio belum lengkap.',
                'missing_segments' => $missing,
                'uploaded_count' => count($storedSeqs),
                'expected_segments' => $expected,
            ], 422);
        }

        $now = now();
        $retentionMonths = (int) config('recording.retention_months', 1);

        $meeting->update([
            'status' => 'uploaded',
            'expected_segments' => $expected,
            'ended_at' => $now,
            'audio_saved_at' => $now,
            'audio_expires_at' => $now->copy()->addMonths($retentionMonths),
        ]);

        MeetingAudioUploaded::dispatch($meeting);

        Audit::record('recording_finish', $meeting->id, [
            'expected_segments' => $expected,
            'audio_saved_at' => $now->toIso8601String(),
            'audio_expires_at' => $meeting->audio_expires_at ? Carbon::parse($meeting->audio_expires_at)->toIso8601String() : null,
        ]);

        return response()->json([
            'completed' => true,
            'message' => 'Perekaman berhasil diselesaikan dan seluruh segmen audio tersimpan.',
            'meeting' => $meeting->fresh(),
            'redirect_url' => route('meetings.show', $meeting),
        ]);
    }

    /**
     * Retry transcription for failed segments in a meeting.
     */
    public function retryTranscription(Meeting $meeting): JsonResponse
    {
        $this->authorize('record', $meeting);

        $failedSegments = $meeting->segments()
            ->whereIn('status', ['failed', 'uploaded'])
            ->orderBy('seq')
            ->get();

        if ($failedSegments->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada segmen audio yang perlu dicoba ulang.',
            ], 422);
        }

        $meeting->update(['status' => 'transcribing']);

        foreach ($failedSegments as $segment) {
            $segment->update(['status' => 'uploaded']);
            TranscribeSegment::dispatch($segment);
        }

        Audit::record('stt_retry_manual', $meeting->id, [
            'segments_count' => $failedSegments->count(),
        ]);

        return response()->json([
            'message' => "Proses transkripsi untuk {$failedSegments->count()} segmen berhasil dijadwalkan ulang.",
            'retried_count' => $failedSegments->count(),
        ]);
    }
}
