<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CupTopup extends Model
{
    protected $fillable = [
        'date', 'branch_id', 'cup_type_id',
        'quantity', 'logged_by', 'logged_at',
    ];

    protected $casts = [
        'date'      => 'date',
        'logged_at' => 'datetime',
    ];

    public function cupType() { return $this->belongsTo(CupType::class); }
    public function branch()  { return $this->belongsTo(Branch::class); }
}
