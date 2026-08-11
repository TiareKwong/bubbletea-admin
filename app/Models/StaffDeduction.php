<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDeduction extends Model
{
    protected $fillable = [
        'user_id', 'date', 'amount', 'reason', 'source', 'cash_reconciliation_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'   => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashReconciliation(): BelongsTo
    {
        return $this->belongsTo(CashReconciliation::class);
    }
}
