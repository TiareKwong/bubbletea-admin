<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayRunEntry extends Model
{
    protected $fillable = [
        'pay_run_id', 'user_id', 'hourly_rate', 'total_hours',
        'gross_pay', 'employee_kpf', 'employer_kpf', 'net_pay',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate'   => 'decimal:2',
            'total_hours'   => 'decimal:2',
            'gross_pay'     => 'decimal:2',
            'employee_kpf'  => 'decimal:2',
            'employer_kpf'  => 'decimal:2',
            'net_pay'       => 'decimal:2',
        ];
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(PayRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
