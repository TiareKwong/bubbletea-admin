<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCategory extends Model
{
    protected $fillable = ['name'];

    public function stockItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockItem::class, 'category', 'name');
    }
}
