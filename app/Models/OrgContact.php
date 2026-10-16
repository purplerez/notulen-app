<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgContact extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'org_contacts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'position',
        'phone_e164',
    ];

    /**
     * Set the phone_e164 attribute with normalization.
     */
    public function setPhoneE164Attribute(?string $value): void
    {
        $this->attributes['phone_e164'] = PhoneNumber::normalize($value) ?? $value;
    }

    /**
     * Get attendees linked to this contact.
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class, 'contact_id');
    }
}
