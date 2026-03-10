<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AngelLoginController;
use App\Http\Controllers\AngelProfileController;
use App\Http\Controllers\NiftyController;
use App\Http\Controllers\NiftyOptionDataController;
use App\Http\Controllers\CrudeOptionDataController;
use App\Http\Controllers\AIAnalysisController;
use App\Http\Controllers\SensexOptionDataController;

// Login
Route::get('/login-process', [AngelLoginController::class, 'login'])->name('angel.login');
Route::post('/logout', [AngelLoginController::class, 'logout'])->name('logout');

Route::middleware(['angel.auth'])->group(function () {
    Route::group(['prefix' => 'angel', 'as' => 'angel.'], function () {

        // Dashboard & Market
        Route::get('/home',        [AngelProfileController::class, 'home'])         ->name('home');
        Route::get('/market-data', [AngelProfileController::class, 'getMarketData'])->name('market.json');

        // Nifty Chart
        Route::get('/nifty',            [NiftyController::class, 'chart'])         ->name('nifty.chart');
        Route::get('/nifty/historical', [NiftyController::class, 'historicalData'])->name('nifty.historical');

        // Nifty Option Chain
        Route::get('/option-data',         [NiftyOptionDataController::class, 'index'])             ->name('option-data');
        Route::get('/option-data/refresh', [NiftyOptionDataController::class, 'refreshOptionsData'])->name('option-data.refresh');
        Route::get('/nifty-option-chain',  [NiftyOptionDataController::class, 'index'])             ->name('nifty.option-chain');
        Route::get('/chain-refresh',       [NiftyOptionDataController::class, 'refreshChainData'])  ->name('chain.refresh');
        Route::get('/candle-data',         [NiftyOptionDataController::class, 'getCandleData'])     ->name('angel.candle.data');

        // Crude Oil
        Route::get('/crude-option',         [CrudeOptionDataController::class, 'index'])           ->name('crude-option');
        Route::get('/crude-option/refresh', [CrudeOptionDataController::class, 'refreshCrudeData'])->name('crude-option.refresh');
        Route::get('/crude-chart',          [CrudeOptionDataController::class, 'chart'])           ->name('crude-chart');

        // ✅ Sensex Option Chain — sahi routes, koi double prefix nahi
        Route::get('/sensex-option-chain',  [SensexOptionDataController::class, 'index'])           ->name('sensex.option-chain');
        Route::get('/sensex-candle-data',   [SensexOptionDataController::class, 'getCandleData'])   ->name('sensex.candle.data');
       
        Route::get('/sensex-live-tick',     [SensexOptionDataController::class, 'getLiveTick'])     ->name('sensex.live.tick'); // ✅ /angel/sensex-live-tick

        // AI — Nifty
        Route::post('/ai-analyze',       [AIAnalysisController::class, 'analyze'])   ->name('ai.analyze');
        Route::post('/chart-chat',       [AIAnalysisController::class, 'chartChat']) ->name('chart.chat');
        Route::post('/nifty-ai-analyze', [AIAnalysisController::class, 'niftyAnalyze'])->name('nifty.ai.analyze');
        Route::post('/nifty-chat',       [AIAnalysisController::class, 'niftyChat'])   ->name('nifty.chat');

        // AI — Sensex
        Route::post('/sensex-ai-analyze', [AIAnalysisController::class, 'sensexAnalyze'])  ->name('sensex.ai.analyze');
        Route::post('/sensex-chat',       [AIAnalysisController::class, 'sensexChat'])     ->name('sensex.chat');
        Route::post('/sensex-chart-chat', [AIAnalysisController::class, 'sensexChartChat'])->name('sensex.chart.chat');
        Route::get('/sensex-chain-refresh', [SensexOptionDataController::class, 'chainRefresh'])->name('sensex.chain.refresh');
       Route::get('/sensex-debug-tokens', [SensexOptionDataController::class, 'debugTokens'])->name('sensex.debug.tokens');
            Route::get('/sensex-debug',        [SensexOptionDataController::class, 'debug'])       ->name('sensex.debug');

            Route::get('/sensex-fix-spot', function() {
    // Manually real spot cache mein daalo
    \Illuminate\Support\Facades\Cache::put('sensex_spot_last', 78918.9, now()->addHours(8));
    return response()->json(['done' => true, 'spot_set' => 78918.9]);
});

    });
});
