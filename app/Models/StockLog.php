<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'stock_item_id',
        'type',
        'quantity',
        'branch_id',
        'notes',
        'created_by',
        'logged_at',
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'logged_at' => 'datetime',
    ];

    public function stockItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
