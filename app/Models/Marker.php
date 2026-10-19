<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $meeting_id
 * @property string $type
 * @property float $at_sec
 * @property string|null $note
 */
class Marker extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'markers';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'type',
        'at_sec',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'at_sec' => 'decimal:2',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
