<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transcript_segments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'audio_segment_id',
        'start_sec',
        'end_sec',
        'text',
        'edited_text',
        'edited_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_sec' => 'float',
            'end_sec' => 'float',
        ];
    }

    /**
     * The meeting this transcript segment belongs to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * The original audio segment this transcript is derived from.
     *
     * @return BelongsTo<AudioSegment, $this>
     */
    public function audioSegment(): BelongsTo
    {
        return $this->belongsTo(AudioSegment::class);
    }

    /**
     * The user who manually edited the text (if any).
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Get the formatted start time in MM:SS format.
     */
    public function getFormattedStartTime(): string
    {
        $minutes = (int) floor($this->start_sec / 60);
        $seconds = (int) floor(fmod($this->start_sec, 60));

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get the formatted end time in MM:SS format.
     */
    public function getFormattedEndTime(): string
    {
        $minutes = (int) floor($this->end_sec / 60);
        $seconds = (int) floor(fmod($this->end_sec, 60));

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Determine whether the segment text has been manually edited.
     */
    public function isEdited(): bool
    {
        return $this->edited_text !== null && $this->edited_text !== $this->text;
    }

    /**
     * Get the effective text for display (edited text if available, otherwise original text).
     */
    public function getEffectiveText(): string
    {
        return $this->edited_text !== null ? $this->edited_text : $this->text;
    }
}
