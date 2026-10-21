<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property float $usd_idr
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static> activeAt(Carbon|string $date)
 */
class FxRate extends Model
{
    use HasFactory;

    protected $table = 'fx_rates';

    protected $fillable = [
        'date',
        'usd_idr',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'usd_idr' => 'float',
        ];
    }

    /**
     * Scope query to find latest FX rate on or before a given date.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveAt(Builder $query, Carbon|string $date): void
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;
        $query->where('date', '<=', $dateStr)
            ->orderBy('date', 'desc');
    }
}
