<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayRun extends Model
{
    protected $fillable = [
        'week_start', 'week_end', 'status', 'paid_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end'   => 'date',
            'paid_at'    => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayRunEntry::class);
    }

    public function getTotalGrossAttribute(): float
    {
        return (float) $this->entries->sum('gross_pay');
    }

    public function getTotalNetAttribute(): float
    {
        return (float) $this->entries->sum('net_pay');
    }

    public function getTotalEmployerKpfAttribute(): float
    {
        return (float) $this->entries->sum('employer_kpf');
    }
}
