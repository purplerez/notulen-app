<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $meeting_id
 * @property int $version
 * @property string $status
 * @property array<string, mixed> $content_json
 * @property int $created_by
 * @property Carbon|null $finalized_at
 * @property string|null $revision_reason
 * @property-read Meeting|null $meeting
 * @property-read User|null $creator
 * @property-read Collection<int, MinuteAcknowledgement> $acknowledgements
 * @property-read Collection<int, ActionItem> $actionItems
 */
class Minute extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'minutes';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'version',
        'status',
        'content_json',
        'created_by',
        'finalized_at',
        'revision_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'content_json' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MinuteAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(MinuteAcknowledgement::class);
    }

    /**
     * @return HasMany<ActionItem, $this>
     */
    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    public function isAcknowledgedBy(User $user): bool
    {
        return $this->acknowledgements()->where('user_id', $user->id)->exists();
    }

    public function getSection(string $key, mixed $default = null): mixed
    {
        $content = $this->content_json ?? [];

        return $content[$key] ?? $default;
    }
}
