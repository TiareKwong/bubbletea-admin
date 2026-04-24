<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WalletTopupRequest extends Model
{
    // MySQL TIMESTAMP columns come back as UTC strings; parse them explicitly
    // as UTC so Filament's timezone conversion works correctly.
    public function getCreatedAtAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    public function getUpdatedAtAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC') : null;
    }

    protected $fillable = [
        'user_id',
        'branch_id',
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

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }
}
