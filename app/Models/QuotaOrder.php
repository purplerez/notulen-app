<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuotaOrder extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'order_number',
        'hours',
        'minutes',
        'price_per_hour',
        'total_amount',
        'status',
        'payment_method',
        'payment_proof_path',
        'paid_at',
        'created_by',
        'approved_by',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'minutes' => 'integer',
            'price_per_hour' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Generate a unique order number (e.g. QO-202610-A1B2C).
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'QO-'.date('Ym').'-';
        do {
            $candidate = $prefix.strtoupper(Str::random(5));
        } while (static::withoutGlobalScopes()->where('order_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Organization relationship.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * User who created this order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Super Admin who verified/approved this order.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isWaitingVerification(): bool
    {
        return $this->status === 'waiting_verification';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
