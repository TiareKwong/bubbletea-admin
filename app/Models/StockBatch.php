<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    protected $fillable = [
        'stock_item_id',
        'quantity',
        'initial_quantity',
        'expiry_date',
        'notes',
        'received_at',
        'created_by',
    ];

    protected $casts = [
        'quantity'         => 'decimal:2',
        'initial_quantity' => 'decimal:2',
        'expiry_date'      => 'date',
        'received_at'      => 'datetime',
    ];

    public function stockItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiry_date !== null
            && ! $this->expiry_date->isPast()
            && $this->expiry_date->diffInDays(now()) <= 30;
    }
}
