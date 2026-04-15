<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topping extends Model
{
    // The toppings table has both created_at and updated_at
    protected $fillable = [
        'name',
        'price',
        'image_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
