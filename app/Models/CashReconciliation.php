<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashReconciliation extends Model
{
    protected $fillable = [
        'reconciliation_date',
        'payment_method',
        'expected_cash',
        'actual_cash',
        'difference',
        'notes',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'expected_cash'       => 'decimal:2',
            'actual_cash'         => 'decimal:2',
            'difference'          => 'decimal:2',
            'submitted_at'        => 'datetime',
        ];
    }
}
