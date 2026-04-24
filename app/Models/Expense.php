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
        'paid_from',
        'purchased_by',
        'expense_date',
        'notes',
        'created_by',
        'branch_id',
        'reimbursement_status',
        'reimbursement_payment_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            if ($expense->paid_from === 'own_money' && $expense->reimbursement_status === null) {
                $expense->reimbursement_status = 'unpaid';
            }
            if ($expense->paid_from === 'cash_box') {
                $expense->reimbursement_status = null;
                $expense->reimbursement_payment_id = null;
            }
        });
    }

    public function reimbursementPayment()
    {
        return $this->belongsTo(ReimbursementPayment::class);
    }

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
