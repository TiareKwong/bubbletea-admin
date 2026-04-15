<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    // promotions table has created_at but no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'description',
        'image_url',
        'status',
        'valid_from',
        'valid_until',
        'type',
        'buy_quantity',
        'free_quantity',
        'free_item_size',
        'free_item_category',
        'discount_percent',
        'applies_to',
        'target_category',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'       => 'date',
            'valid_until'      => 'date',
            'buy_quantity'     => 'integer',
            'free_quantity'    => 'integer',
            'discount_percent' => 'decimal:2',
        ];
    }
}
