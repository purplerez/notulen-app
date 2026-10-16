<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendees';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'contact_id',
        'name',
        'position',
        'phone_e164',
        'status',
        'checked_in_at',
        'method',
        'is_guest',
        'guest_status',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'is_guest' => 'boolean',
        ];
    }

    /**
     * Automatically normalize phone number to E.164.
     */
    public function setPhoneE164Attribute(?string $value): void
    {
        $this->attributes['phone_e164'] = PhoneNumber::normalize($value) ?? $value;
    }

    /**
     * The meeting this attendee belongs to.
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * The organization contact this attendee is linked to (if any).
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(OrgContact::class, 'contact_id');
    }
}
