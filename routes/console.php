<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('promotions:deactivate-expired')->daily();

// 20:00 UTC = 8:00 AM Pacific/Tarawa (UTC+12)
Schedule::call(function () {
    $batches = \App\Models\StockBatch::with('stockItem')
        ->where('quantity', '>', 0)
        ->whereNotNull('expiry_date')
        ->whereDate('expiry_date', '>=', now('Pacific/Tarawa')->toDateString())
        ->whereDate('expiry_date', '<=', now('Pacific/Tarawa')->addDays(30)->toDateString())
        ->orderBy('expiry_date')
        ->get();

    if ($batches->isNotEmpty()) {
        (new \Illuminate\Notifications\AnonymousNotifiable)
            ->notify(new \App\Notifications\ExpiringBatchAlert($batches));
    }
})->dailyAt('20:00')->name('stock:expiry-alert');
