<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'address_text',
        'kop_path',
        'plan',
        'quota_minutes',
        'parent_id',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'quota_minutes' => 'integer',
        ];
    }

    /**
     * Get the users belonging to this organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the parent organization (if hierarchical).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the sub-organizations.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the audit logs for this organization.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the contacts for this organization.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(OrgContact::class);
    }

    /**
     * Get the signatories for this organization.
     */
    public function signatories(): HasMany
    {
        return $this->hasMany(OrganizationSignatory::class);
    }

    /**
     * Get the meetings for this organization.
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
