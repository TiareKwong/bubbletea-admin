<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    // rewards table has updated_at but no created_at
    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'points',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
