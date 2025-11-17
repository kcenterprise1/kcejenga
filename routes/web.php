<?php

use Illuminate\Support\Facades\Route;
use Kce\Kcejenga\Http\Controllers\PaymentCallbackController;
use Kce\Kcejenga\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Jenga Payment Gateway Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the KcejengaServiceProvider.
|
*/

// Payment callback route (public, no auth required)
// Jenga uses GET method for callbacks, but we support both GET and POST
Route::match(['get', 'post'], '/kcejenga/callback', [PaymentCallbackController::class, 'handle'])
    ->name('kcejenga.callback');

// Transaction status check (public)
Route::get('/kcejenga/status', [PaymentCallbackController::class, 'status'])
    ->name('kcejenga.status');

// Settings routes (protected - requires authentication)
Route::prefix('kcejenga')->middleware('auth')->name('kcejenga.')->group(function () {
    Route::get('/settings', [SettingsController::class, 'show'])
        ->name('settings.show');
    
    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update');
    
    Route::post('/settings/test', [SettingsController::class, 'testConnection'])
        ->name('settings.test');
});

