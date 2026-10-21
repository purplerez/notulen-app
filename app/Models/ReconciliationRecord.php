<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $month
 * @property string $provider
 * @property float|null $actual_cost_usd
 * @property float|null $actual_cost_idr
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReconciliationRecord extends Model
{
    use HasFactory;

    protected $table = 'reconciliation_records';

    protected $fillable = [
        'month',
        'provider',
        'actual_cost_usd',
        'actual_cost_idr',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'actual_cost_usd' => 'float',
            'actual_cost_idr' => 'float',
        ];
    }

    /**
     * The super admin who recorded or updated this entry.
     *
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'recorded_by');
    }
}
