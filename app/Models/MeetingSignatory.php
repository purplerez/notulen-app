<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MeetingSignatory extends Pivot
{
    use BelongsToOrganization;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'meeting_signatories';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'signatory_id',
        'sort',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    /**
     * Meeting relationship.
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Signatory relationship.
     */
    public function signatory(): BelongsTo
    {
        return $this->belongsTo(OrganizationSignatory::class, 'signatory_id');
    }
}
