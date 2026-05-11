<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCupLog extends Model
{
    protected $fillable = [
        'date', 'branch_id', 'cup_type_id',
        'opening', 'opening_by', 'closing', 'closing_by',
        'reusable_returns', 'logged_by',
    ];

    protected $casts = ['date' => 'date'];

    public function cupType()  { return $this->belongsTo(CupType::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
}
