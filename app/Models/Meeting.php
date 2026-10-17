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
}
