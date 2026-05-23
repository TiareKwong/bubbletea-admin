<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffPushController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::post('/staff/push/subscribe',   [StaffPushController::class, 'subscribe']);
    Route::post('/staff/push/unsubscribe', [StaffPushController::class, 'unsubscribe']);
});
