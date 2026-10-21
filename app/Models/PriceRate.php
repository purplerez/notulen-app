<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property string $unit
 * @property float $price_usd
 * @property Carbon $effective_from
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static> activeAt(Carbon $date)
 */
class PriceRate extends Model
{
    use HasFactory;

    protected $table = 'price_rates';

    protected $fillable = [
        'provider',
        'model',
        'unit',
        'price_usd',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'float',
            'effective_from' => 'datetime',
        ];
    }

    /**
     * Scope query to find rates active at a given date/time.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveAt(Builder $query, Carbon $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->orderBy('effective_from', 'desc');
    }
}
