<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'actor_user_id',
        'actor_attendee_id',
        'action',
        'meta',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Booted method to enforce append-only rules.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('AuditLog is append-only. Updates are not allowed.');
        });

        static::deleting(function () {
            throw new LogicException('AuditLog is append-only. Deletions are not allowed.');
        });
    }

    /**
     * Prevent updates via save().
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('AuditLog is append-only. Updates are not allowed.');
        }

        return parent::save($options);
    }

    /**
     * Prevent deletion.
     */
    public function delete(): ?bool
    {
        throw new LogicException('AuditLog is append-only. Deletions are not allowed.');
    }

    /**
     * Actor user relation.
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
