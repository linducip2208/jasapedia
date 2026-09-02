<?php

namespace App\Models;

use App\Domain\Order\OrderStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const TYPE_SERVICE = 'service';

    public const TYPE_PROJECT = 'project';

    public const TYPE_MILESTONE_FUNDING = 'milestone_funding';

    public const TYPE_ADDITIONAL_CHARGE = 'additional_charge';

    protected $fillable = [
        'code', 'user_id', 'partner_id', 'organization_id', 'type', 'status',
        'active_status_snapshot', 'service_id', 'package_id', 'fulfillment_type', 'delivery_mode',
        'scheduled_at', 'duration_minutes', 'address_id', 'address_snapshot', 'slot_id',
        'customer_note', 'attachments', 'pricing_snapshot', 'subtotal', 'emergency_surcharge', 'total',
        'is_emergency', 'paid_at', 'completed_at', 'settled_at', 'cancelled_at', 'cancelled_by',
        'cancel_reason', 'meta', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array', 'attachments' => 'array', 'pricing_snapshot' => 'array',
            'meta' => 'array', 'is_emergency' => 'boolean', 'scheduled_at' => 'datetime',
            'paid_at' => 'datetime', 'completed_at' => 'datetime', 'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= 'JP-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function transition(string $to, ?User $actor = null, ?string $reason = null, array $metadata = []): Order
    {
        return app(OrderStateMachine::class)->transition($this, $to, $actor, $reason, $metadata);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['completed', 'settled', 'closed', 'cancelled', 'expired', 'failed', 'refunded'], true);
    }
}
