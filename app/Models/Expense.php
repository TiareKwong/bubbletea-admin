<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'description',
        'category',
        'amount',
        'purchased_by',
        'expense_date',
        'notes',
        'created_by',
        'branch_id',
    ];

    protected $attributes = [
        'category' => 'Other',
    ];

    protected function purchasedBy(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ?? '');
    }

    protected function notes(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ?? '');
    }

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'expense_date' => 'date',
        ];
    }
}
