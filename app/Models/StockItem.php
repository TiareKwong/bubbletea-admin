<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = [
        'name',
        'category',
        'unit',
        'current_quantity',
        'min_quantity',
        'sea_reorder_quantity',
        'nearest_expiry_date',
        'notes',
    ];

    protected $casts = [
        'current_quantity'    => 'decimal:2',
        'min_quantity'        => 'decimal:2',
        'sea_reorder_quantity' => 'decimal:2',
        'nearest_expiry_date' => 'date',
    ];

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockLog::class)->orderByDesc('logged_at');
    }

    public function batches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockBatch::class)->orderBy('received_at');
    }

    public function activeBatches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockBatch::class)
            ->where('quantity', '>', 0)
            ->orderBy('received_at');
    }

    public function syncFromBatches(): void
    {
        $total = $this->batches()->where('quantity', '>', 0)->sum('quantity');

        $nearestExpiry = $this->batches()
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->value('expiry_date');

        $this->update([
            'current_quantity'    => (float) $total,
            'nearest_expiry_date' => $nearestExpiry,
        ]);
    }

    public function isLowStock(): bool
    {
        return $this->current_quantity > 0 && $this->current_quantity <= $this->min_quantity;
    }

    public function isOrderBySea(): bool
    {
        return $this->sea_reorder_quantity > 0
            && $this->current_quantity > $this->min_quantity
            && $this->current_quantity <= $this->sea_reorder_quantity;
    }

    public function isOutOfStock(): bool
    {
        return $this->current_quantity <= 0;
    }

    public function isExpiringSoon(): bool
    {
        return $this->nearest_expiry_date !== null
            && ! $this->nearest_expiry_date->isPast()
            && $this->nearest_expiry_date->diffInDays(now()) <= 30;
    }

    public function isExpired(): bool
    {
        return $this->nearest_expiry_date !== null && $this->nearest_expiry_date->isPast();
    }
}
