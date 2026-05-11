<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CupType extends Model
{
    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function dailyCupLogs()
    {
        return $this->hasMany(DailyCupLog::class);
    }

    public function cupTopups()
    {
        return $this->hasMany(CupTopup::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /** No entries in pivot = available at all branches. */
    public function isAvailableAt(?int $branchId): bool
    {
        if ($branchId === null) return true;
        if ($this->relationLoaded('branches') && $this->branches->isEmpty()) return true;
        return $this->branches->contains('id', $branchId);
    }
}
