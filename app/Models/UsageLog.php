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
 * @property int|null $meeting_id
 * @property int|null $user_id
 * @property string $kind
 * @property string $provider
 * @property string $model
 * @property string $unit
 * @property float $qty
 * @property float $audio_sec
 * @property float $rate_snapshot
 * @property float $est_cost_usd
 * @property float $est_cost_idr
 * @property float $fx_rate_used
 * @property string $status
 * @property int $attempt
 * @property int|null $http_status
 * @property float|null $est_cost
 * @property Carbon $created_at
 */
class UsageLog extends Model
{
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usage_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'user_id',
        'kind',
        'provider',
        'model',
        'unit',
        'qty',
        'audio_sec',
        'rate_snapshot',
        'est_cost_usd',
        'est_cost_idr',
        'fx_rate_used',
        'status',
        'attempt',
        'http_status',
        'est_cost',
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
            'qty' => 'float',
            'audio_sec' => 'float',
            'rate_snapshot' => 'float',
            'est_cost_usd' => 'float',
            'est_cost_idr' => 'float',
            'fx_rate_used' => 'float',
            'attempt' => 'integer',
            'http_status' => 'integer',
            'est_cost' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The meeting this log belongs to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * The user who triggered the operation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organization this log belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
