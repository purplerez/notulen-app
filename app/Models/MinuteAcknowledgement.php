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
 * @property int $minute_id
 * @property int $user_id
 * @property Carbon $acknowledged_at
 */
class MinuteAcknowledgement extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'minute_acknowledgements';

    protected $fillable = [
        'organization_id',
        'minute_id',
        'user_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
