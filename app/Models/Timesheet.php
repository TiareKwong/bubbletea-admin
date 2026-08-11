<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    protected $fillable = [
        'user_id', 'branch_id', 'work_date', 'time_in', 'time_out', 'hours_worked', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date'    => 'date',
            'hours_worked' => 'decimal:2',
        ];
    }

    public static function calculateHours(?string $timeIn, ?string $timeOut): float
    {
        if (! $timeIn || ! $timeOut) return 0;

        $in  = \Carbon\Carbon::parse($timeIn);
        $out = \Carbon\Carbon::parse($timeOut);

        if ($in->eq($out)) {
            return 0;
        }

        if ($out->lt($in)) {
            $out->addDay();
        }

        return round($in->diffInMinutes($out) / 60, 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
