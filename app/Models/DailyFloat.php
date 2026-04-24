<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyFloat extends Model
{
    protected $fillable = ['branch_id', 'date', 'amount', 'set_by'];

    protected function casts(): array
    {
        return [
            'date'   => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
