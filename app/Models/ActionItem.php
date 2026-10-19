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
 * @property int $minute_id
 * @property string $description
 * @property string|null $assignee_name
 * @property Carbon|null $due_date
 * @property string $status
 */
class ActionItem extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'action_items';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'minute_id',
        'description',
        'assignee_name',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }
}
