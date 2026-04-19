<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Flavor extends Model
{
    // The flavors table has no updated_at column
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'type',
        'category',
        'image_url',
        'status',
        'small_price',
        'small_ml',
        'regular_price',
        'regular_ml',
        'large_price',
        'large_ml',
    ];

    protected function casts(): array
    {
        return [
            'small_price'   => 'decimal:2',
            'regular_price' => 'decimal:2',
            'large_price'   => 'decimal:2',
            'created_at'    => 'datetime',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_flavor');
    }
}
