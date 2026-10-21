<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $totp_secret
 * @property bool $totp_enabled
 * @property Carbon|null $totp_confirmed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SuperAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'super_admins';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'totp_secret',
        'totp_enabled',
        'totp_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_enabled' => 'boolean',
            'totp_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the super admin has successfully confirmed and enabled 2FA.
     */
    public function hasTotpEnabled(): bool
    {
        return $this->totp_enabled && ! empty($this->totp_secret) && $this->totp_confirmed_at !== null;
    }
}
