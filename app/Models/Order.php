<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Order extends Model
{
    // orders table has both created_at and updated_at
    protected $fillable = [
        'user_id',
        'total_price',
        'payment_method',
        'order_code',
        'order_status',
        'payment_reference',
        'reward_redeemed',
        'points_used',
        'points_earned',
        'collected',
        'updated_by',
        'discount_applied',
        'promo_title',
        'free_items',
    ];

    protected function casts(): array
    {
        return [
            'total_price'      => 'decimal:2',
            'discount_applied' => 'decimal:2',
            'free_items'       => 'array',
            'reward_redeemed'  => 'boolean',
            'collected'        => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function booted(): void
    {
        // Clear tab badge caches whenever an order is created or its status changes.
        static::saved(function (Order $order) {
            if ($order->wasChanged('order_status') || $order->wasRecentlyCreated) {
                Cache::forget('tab_needs_attention');
                Cache::forget('tab_pending_payment');
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (static::where('order_code', $code)->exists());

        return $code;
    }
}
