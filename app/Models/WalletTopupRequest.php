<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTopupRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'payment_method',
        'status',
        'notes',
        'actioned_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
