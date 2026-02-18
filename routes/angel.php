<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AngelLoginController;
use App\Http\Controllers\AngelProfileController;
use App\Http\Controllers\NiftyController;
use App\Http\Controllers\NiftyOptionDataController;
use App\Http\Controllers\CrudeOptionDataController;

// Login trigger karne ke liye
// 1. Bilkul alag login route (Prefix ke bina)
Route::get('/login-process', [AngelLoginController::class, 'login'])->name('angel.login');

// 2. Logout
Route::post('/logout', [AngelLoginController::class, 'logout'])->name('logout');


// Protected trading routes
Route::middleware(['angel.auth'])->group(function () {
    Route::group([
        'prefix' => 'angel',
        'as' => 'angel.',
    ], function () {
        // In routes par hum redirect honge login ke baad
        Route::get('/home', [AngelProfileController::class, 'home'])->name('home');
        Route::get('/nifty', [NiftyController::class, 'chart'])->name('nifty.chart');
        Route::get('/market-data', [AngelProfileController::class, 'getMarketData'])->name('market.json');
        Route::get('/option-data', [NiftyOptionDataController::class, 'index'])->name('option-data');
        Route::get('/option-data/refresh', [NiftyOptionDataController::class, 'refreshOptionsData'])->name('option-data.refresh');
        Route::get('/crude-option', [CrudeOptionDataController::class, 'index'])->name('crude-option');
        Route::get('/crude-option/refresh', [CrudeOptionDataController::class, 'refreshCrudeData'])->name('crude-option.refresh');
        Route::get('/crude-chart', [CrudeOptionDataController::class, 'chart'])->name('crude-chart');

    });
});

// SmartAPI Dashboard Redirect URL placeholder
// Route::get('/callback', function() {
//     return "Callback handled successfully!";
// });
// // LIVE Data API (Jo aapne pehle se setup kiya hai)
// Route::get('/charts/market/nifty', [HistoricalOIController::class, 'fetchNiftyData']);

// // HISTORICAL Data API (Ise abhi add karein)
// Route::get('/api/nifty/history', [HistoricalOIController::class, 'fetchNiftyHistory']);