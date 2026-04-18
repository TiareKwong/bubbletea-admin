<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'reference',
        'notes',
        'actioned_by',
        'removed_at',
        'removed_by',
        'removal_reason',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
