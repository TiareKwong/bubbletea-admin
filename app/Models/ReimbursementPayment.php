<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReimbursementPayment extends Model
{
    protected $fillable = [
        'staff_name',
        'amount',
        'payment_method',
        'payment_date',
        'branch_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
