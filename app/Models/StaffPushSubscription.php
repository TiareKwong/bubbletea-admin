<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPushSubscription extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
