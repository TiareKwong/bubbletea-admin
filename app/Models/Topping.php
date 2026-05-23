<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Topping extends Model
{
    // The toppings table has both created_at and updated_at
    protected $fillable = [
        'name',
        'price',
        'image_url',
        'status',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_topping');
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
