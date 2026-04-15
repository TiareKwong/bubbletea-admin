<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    // order_items table has no timestamp columns
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'flavor_id',
        'size',
        'ice',
        'sugar',
        'toppings',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'toppings' => 'array',
            'price'    => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function flavor(): BelongsTo
    {
        return $this->belongsTo(Flavor::class);
    }
}
