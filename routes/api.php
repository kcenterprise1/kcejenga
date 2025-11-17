<?php

use Illuminate\Support\Facades\Route;
use Kce\Kcejenga\Http\Controllers\PaymentCallbackController;
use Kce\Kcejenga\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Jenga Payment Gateway API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the KcejengaServiceProvider.
| They are prefixed with 'api/' automatically by Laravel.
|
*/

// Payment callback route (public, no auth required)
Route::post('/kcejenga/callback', [PaymentCallbackController::class, 'handle'])
    ->name('api.kcejenga.callback');

// Transaction status check (public)
Route::get('/kcejenga/status', [PaymentCallbackController::class, 'status'])
    ->name('api.kcejenga.status');

// Settings routes (protected - add your middleware)
Route::prefix('kcejenga')->name('api.kcejenga.')->group(function () {
    Route::get('/settings', [SettingsController::class, 'show'])
        ->name('settings.show');
    
    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update');
    
    Route::post('/settings/test', [SettingsController::class, 'testConnection'])
        ->name('settings.test');
});

