<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $created_by
 * @property string $title
 * @property string $status
 * @property int $expected_segments
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $audio_saved_at
 * @property Carbon|null $audio_expires_at
 * @property Carbon|null $audio_deleted_at
 */
class Meeting extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'meetings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by',
        'title',
        'meeting_date',
        'start_time',
        'location',
        'style',
        'use_followup',
        'use_kop',
        'use_signature',
        'show_attendance',
        'show_recorded_notice',
        'status',
        'late_after_minutes',
        'absensi_closed_at',
        'started_at',
        'ended_at',
        'expected_segments',
        'audio_saved_at',
        'audio_expires_at',
        'audio_deleted_at',
        'merged_audio_path',
        'share_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'use_followup' => 'boolean',
            'use_kop' => 'boolean',
            'use_signature' => 'boolean',
            'show_attendance' => 'boolean',
            'show_recorded_notice' => 'boolean',
            'late_after_minutes' => 'integer',
            'expected_segments' => 'integer',
            'absensi_closed_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'audio_saved_at' => 'datetime',
            'audio_expires_at' => 'datetime',
            'audio_deleted_at' => 'datetime',
            'share_expires_at' => 'datetime',
        ];
    }

    /**
     * The user who created the meeting.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The attendees registered for the meeting.
     *
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * The signatories chosen for this meeting.
     */
    public function signatories(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationSignatory::class,
            'meeting_signatories',
            'meeting_id',
            'signatory_id'
        )->withPivot('sort')->withTimestamps();
    }

    /**
     * Meeting signatories pivot rows.
     */
    public function meetingSignatories(): HasMany
    {
        return $this->hasMany(MeetingSignatory::class);
    }

    /**
     * The recorded audio segments for this meeting.
     *
     * @return HasMany<AudioSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(AudioSegment::class)->orderBy('seq');
    }

    /**
     * The transcribed segments for this meeting.
     *
     * @return HasMany<TranscriptSegment, $this>
     */
    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('start_sec');
    }

    /**
     * The usage logs for external API calls of this meeting.
     *
     * @return HasMany<UsageLog, $this>
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    /**
     * Determine whether the meeting minutes are finalized.
     * When minutes feature is developed (sections 8.4-8.5), check finalized minute versions.
     */
    public function isMinutesFinal(): bool
    {
        return false;
    }

    /**
     * Calculate difference statistics between original text and edited text for this meeting.
     *
     * @return array{
     *     difference_percent: float,
     *     total_segments: int,
     *     edited_segments: int,
     *     edited_segments_percent: float,
     *     total_words_original: int,
     *     total_words_edited: int
     * }
     */
    public function getTranscriptStats(): array
    {
        $segments = $this->transcriptSegments()->get();

        $totalSegments = $segments->count();
        if ($totalSegments === 0) {
            return [
                'difference_percent' => 0.0,
                'total_segments' => 0,
                'edited_segments' => 0,
                'edited_segments_percent' => 0.0,
                'total_words_original' => 0,
                'total_words_edited' => 0,
            ];
        }

        $editedSegments = 0;
        $originalTexts = [];
        $effectiveTexts = [];

        foreach ($segments as $seg) {
            $orig = (string) $seg->text;
            $eff = $seg->edited_text !== null ? (string) $seg->edited_text : $orig;

            if ($seg->edited_text !== null && $seg->edited_text !== $seg->text) {
                $editedSegments++;
            }

            $originalTexts[] = $orig;
            $effectiveTexts[] = $eff;
        }

        $allOriginal = trim(implode(' ', $originalTexts));
        $allEffective = trim(implode(' ', $effectiveTexts));

        $similarity = 100.0;
        if ($allOriginal !== '' || $allEffective !== '') {
            similar_text($allOriginal, $allEffective, $similarity);
        }

        $diffPercent = round(max(0.0, 100.0 - $similarity), 1);
        $editedPercent = round(($editedSegments / $totalSegments) * 100.0, 1);

        $wordsOriginal = str_word_count($allOriginal);
        $wordsEdited = str_word_count($allEffective);

        return [
            'difference_percent' => $diffPercent,
            'total_segments' => $totalSegments,
            'edited_segments' => $editedSegments,
            'edited_segments_percent' => $editedPercent,
            'total_words_original' => $wordsOriginal,
            'total_words_edited' => $wordsEdited,
        ];
    }
}
