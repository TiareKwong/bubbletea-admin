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
        'nearest_expiry_date',
        'notes',
    ];

    protected $casts = [
        'current_quantity'    => 'decimal:2',
        'min_quantity'        => 'decimal:2',
        'nearest_expiry_date' => 'date',
    ];

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockLog::class)->orderByDesc('logged_at');
    }

    public function isLowStock(): bool
    {
        return $this->current_quantity > 0 && $this->current_quantity <= $this->min_quantity;
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
