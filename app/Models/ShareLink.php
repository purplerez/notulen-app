<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $meeting_id
 * @property string $token_hash
 * @property string $access_mode
 * @property string|null $code_hash
 * @property string $recipients_scope
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int $failed_attempts
 * @property Carbon|null $locked_until
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShareLink extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'share_links';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'token_hash',
        'access_mode',
        'code_hash',
        'recipients_scope',
        'expires_at',
        'revoked_at',
        'failed_attempts',
        'locked_until',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'locked_until' => 'datetime',
            'failed_attempts' => 'integer',
        ];
    }

    /**
     * The meeting this share link belongs to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * The user who created the share link.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine whether the share link is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine whether the share link was manually revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Determine whether the share link is temporarily locked due to failed attempts.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Determine whether the share link is valid for gate verification.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked() && ! $this->isLocked();
    }
}
