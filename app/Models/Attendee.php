<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'signature_path',
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

    /**
     * Determine whether the attendee has a saved digital signature.
     */
    public function hasSignature(): bool
    {
        return ! empty($this->signature_path);
    }

    /**
     * Get the attendee's signature as a Base64 data URI for embedding in PDF / HTML.
     */
    public function getSignatureBase64(): ?string
    {
        if (empty($this->signature_path)) {
            return null;
        }

        $disk = config('recording.disk', 'local');
        $storage = Storage::disk($disk);

        if (! $storage->exists($this->signature_path)) {
            return null;
        }

        $content = $storage->get($this->signature_path);
        if (empty($content)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($content);
    }
}
