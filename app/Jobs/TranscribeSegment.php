<?php

namespace App\Jobs;

use App\Contracts\SpeechToTextProvider;
use App\Exceptions\RateLimitException;
use App\Exceptions\TranscriptionException;
use App\Models\AudioSegment;
use App\Models\TranscriptSegment;
use App\Models\UsageLog;
use App\Notifications\SttQuotaExceededNotification;
use App\Services\Cost\CostCalculator;
use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeSegment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AudioSegment $segment
    ) {
        $this->onQueue('stt');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('stt')];
    }

    /**
     * Execute the job.
     */
    public function handle(SpeechToTextProvider $provider): void
    {
        $meeting = $this->segment->meeting;
        $audioSec = (float) ($this->segment->duration_sec ?: 60.0);
        $providerName = (string) config('stt.driver', 'groq');
        $modelName = (string) config("stt.drivers.{$providerName}.model", 'whisper-large-v3-turbo');
        $attempt = $this->attempts();

        // 1. Check ASH Limit (Audio Seconds per Hour across platform)
        $ashLimit = (int) config('stt.ash_limit', 7000);
        $recentAudioSec = (float) UsageLog::where('kind', 'stt')
            ->where('status', 'sukses')
            ->where('created_at', '>=', now()->subHour())
            ->sum('audio_sec');

        if (($recentAudioSec + $audioSec) > $ashLimit) {
            $cost = CostCalculator::calculate($providerName, $modelName, 'detik_audio', $audioSec);

            UsageLog::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->created_by,
                'kind' => 'stt',
                'provider' => $providerName,
                'model' => $modelName,
                'unit' => 'detik_audio',
                'qty' => $audioSec,
                'audio_sec' => $audioSec,
                'rate_snapshot' => $cost['rate_snapshot'],
                'est_cost_usd' => $cost['est_cost_usd'],
                'est_cost_idr' => $cost['est_cost_idr'],
                'fx_rate_used' => $cost['fx_rate_used'],
                'est_cost' => $cost['est_cost_usd'],
                'status' => 'ditahan',
                'attempt' => $attempt,
                'http_status' => null,
            ]);

            $oldest = UsageLog::where('kind', 'stt')
                ->where('status', 'sukses')
                ->where('created_at', '>=', now()->subHour())
                ->orderBy('created_at', 'asc')
                ->first();

            $delay = $oldest
                ? max(15, (int) now()->diffInSeconds($oldest->created_at->addHour()))
                : 60;

            $this->release($delay);

            return;
        }

        // 2. Locate audio file in private storage
        $disk = config('recording.disk', 'local');
        $storage = Storage::disk($disk);

        if (! $storage->exists($this->segment->path)) {
            throw new TranscriptionException("Berkas audio segmen tidak ditemukan di storage: {$this->segment->path}");
        }

        $fullPath = $storage->path($this->segment->path);

        // 3. Call STT Provider
        try {
            $this->segment->update(['status' => 'transcribing']);
            $result = $provider->transcribe($fullPath, $this->segment->mime, config('stt.language', 'id'));
        } catch (RateLimitException $e) {
            $cost = CostCalculator::calculate($providerName, $modelName, 'detik_audio', $audioSec);

            UsageLog::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->created_by,
                'kind' => 'stt',
                'provider' => $providerName,
                'model' => $modelName,
                'unit' => 'detik_audio',
                'qty' => $audioSec,
                'audio_sec' => $audioSec,
                'rate_snapshot' => $cost['rate_snapshot'],
                'est_cost_usd' => $cost['est_cost_usd'],
                'est_cost_idr' => $cost['est_cost_idr'],
                'fx_rate_used' => $cost['fx_rate_used'],
                'est_cost' => $cost['est_cost_usd'],
                'status' => 'gagal',
                'attempt' => $attempt,
                'http_status' => 429,
            ]);

            if ($e->isDailyQuota()) {
                // Quota condition: set waiting_quota, notify notulis, reschedule without failing
                $meeting->update(['status' => 'waiting_quota']);
                $meeting->creator?->notify(new SttQuotaExceededNotification($meeting, $e->getRetryAfterSeconds()));
                Audit::record('stt_waiting_quota', $meeting->id, [
                    'retry_after' => $e->getRetryAfterSeconds(),
                    'segment_id' => $this->segment->id,
                ]);

                $this->release($e->getRetryAfterSeconds());

                return;
            }

            // Burst RPM rate limit: release for retry-after
            $this->release($e->getRetryAfterSeconds());

            return;
        } catch (Throwable $e) {
            $httpStatus = (int) ($e->getCode() ?: 500);
            $cost = CostCalculator::calculate($providerName, $modelName, 'detik_audio', $audioSec);

            UsageLog::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->created_by,
                'kind' => 'stt',
                'provider' => $providerName,
                'model' => $modelName,
                'unit' => 'detik_audio',
                'qty' => $audioSec,
                'audio_sec' => $audioSec,
                'rate_snapshot' => $cost['rate_snapshot'],
                'est_cost_usd' => $cost['est_cost_usd'],
                'est_cost_idr' => $cost['est_cost_idr'],
                'fx_rate_used' => $cost['fx_rate_used'],
                'est_cost' => $cost['est_cost_usd'],
                'status' => 'gagal',
                'attempt' => $attempt,
                'http_status' => $httpStatus > 0 ? $httpStatus : 500,
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(pow(2, $attempt) * 10);

                return;
            }

            // Attempts exhausted: mark segment failed, preserve audio file, allow manual retry
            $this->segment->update(['status' => 'failed']);
            $meeting->update(['status' => 'failed']);
            Audit::record('stt_failed', $meeting->id, [
                'segment_id' => $this->segment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // 4. Record Successful Usage
        $finalDuration = (float) ($result->duration ?: $audioSec);
        $cost = CostCalculator::calculate($providerName, $modelName, 'detik_audio', $finalDuration);

        UsageLog::create([
            'organization_id' => $meeting->organization_id,
            'meeting_id' => $meeting->id,
            'user_id' => $meeting->created_by,
            'kind' => 'stt',
            'provider' => $providerName,
            'model' => $modelName,
            'unit' => 'detik_audio',
            'qty' => $finalDuration,
            'audio_sec' => $finalDuration,
            'rate_snapshot' => $cost['rate_snapshot'],
            'est_cost_usd' => $cost['est_cost_usd'],
            'est_cost_idr' => $cost['est_cost_idr'],
            'fx_rate_used' => $cost['fx_rate_used'],
            'est_cost' => $cost['est_cost_usd'],
            'status' => 'sukses',
            'attempt' => $attempt,
            'http_status' => 200,
        ]);

        // 5. Save Transcript Segments with Sequential Offset (Idempotent)
        TranscriptSegment::where('audio_segment_id', $this->segment->id)->delete();

        $offset = (float) AudioSegment::where('meeting_id', $meeting->id)
            ->where('seq', '<', $this->segment->seq)
            ->sum('duration_sec');

        if (! empty($result->segments)) {
            foreach ($result->segments as $segItem) {
                TranscriptSegment::create([
                    'organization_id' => $meeting->organization_id,
                    'meeting_id' => $meeting->id,
                    'audio_segment_id' => $this->segment->id,
                    'start_sec' => round($offset + $segItem['start'], 2),
                    'end_sec' => round($offset + $segItem['end'], 2),
                    'text' => trim($segItem['text']),
                    'edited_text' => null,
                    'edited_by' => null,
                ]);
            }
        } else {
            TranscriptSegment::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'audio_segment_id' => $this->segment->id,
                'start_sec' => round($offset, 2),
                'end_sec' => round($offset + ($result->duration ?: $audioSec), 2),
                'text' => trim($result->text),
                'edited_text' => null,
                'edited_by' => null,
            ]);
        }

        $this->segment->update(['status' => 'ready']);

        // 6. If transcript polishing is enabled, dispatch PolishTranscript job.
        // AssembleTranscript will be dispatched by PolishTranscript once all segments are done.
        // Otherwise, check directly whether all segments are ready and dispatch assembly.
        if (config('summarizer.polish_enabled', false)) {
            PolishTranscript::dispatch($this->segment);
        } else {
            $readyCount = AudioSegment::where('meeting_id', $meeting->id)
                ->where('status', 'ready')
                ->count();

            if ($meeting->expected_segments > 0 && $readyCount >= $meeting->expected_segments) {
                AssembleTranscript::dispatch($meeting);
            }
        }
    }
}
