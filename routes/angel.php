<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AngelLoginController;
use App\Http\Controllers\AngelProfileController;
use App\Http\Controllers\NiftyController;
use App\Http\Controllers\NiftyOptionDataController;
use App\Http\Controllers\AIAnalysisController;
use App\Http\Controllers\SensexOptionDataController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Public Routes (No Auth Needed)
|--------------------------------------------------------------------------
*/
Route::get('/login-process', [AngelLoginController::class, 'login'])->name('angel.login');


/*
|--------------------------------------------------------------------------
| Protected Angel One Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['angel.auth'])->group(function () {
    
    // Prefix 'angel' means all URLs will start with /angel/...
    Route::group(['prefix' => 'angel', 'as' => 'angel.'], function () {

        // --- DASHBOARD & PROFILE ---
        Route::get('/home', [AngelProfileController::class, 'home'])->name('home');
        Route::get('/market-data', [AngelProfileController::class, 'getMarketData'])->name('market.json');

        // --- NIFTY TERMINAL (Chart & Historical) ---
        Route::get('/nifty', [NiftyController::class, 'chart'])->name('nifty.chart');
        Route::get('/nifty/historical', [NiftyController::class, 'historicalData'])->name('nifty.historical');
        Route::get('/nifty/ltp', [NiftyController::class, 'getLiveLtp'])->name('nifty.ltp');

        // --- NIFTY OPTION CHAIN ---
        Route::get('/option-data', [NiftyOptionDataController::class, 'index'])->name('option-data');
        Route::get('/option-data/refresh', [NiftyOptionDataController::class, 'refreshOptionsData'])->name('option-data.refresh');
        Route::get('/nifty-option-chain', [NiftyOptionDataController::class, 'index'])->name('nifty.option-chain');
        Route::get('/chain-refresh', [NiftyOptionDataController::class, 'refreshChainData'])->name('chain.refresh');
        Route::get('/candle-data', [NiftyOptionDataController::class, 'getCandleData'])->name('candle.data');

        // --- SENSEX OPTION CHAIN ---
        Route::get('/sensex-option-chain', [SensexOptionDataController::class, 'index'])->name('sensex.option-chain');
        Route::get('/sensex-candle-data', [SensexOptionDataController::class, 'getCandleData'])->name('sensex.candle.data');
        Route::get('/sensex-live-tick', [SensexOptionDataController::class, 'getLiveTick'])->name('sensex.live.tick');
        Route::get('/sensex-chain-refresh', [SensexOptionDataController::class, 'chainRefresh'])->name('sensex.chain.refresh');

        // --- AI ANALYSIS (NIFTY) ---
        Route::post('/nifty-ai-analyze', [AIAnalysisController::class, 'niftyAnalyze'])->name('nifty.ai.analyze');
        Route::post('/nifty-chat', [AIAnalysisController::class, 'niftyChat'])->name('nifty.chat');
        Route::post('/chart-chat', [AIAnalysisController::class, 'chartChat'])->name('chart.chat');

        // --- AI ANALYSIS (SENSEX) ---
        Route::post('/sensex-ai-analyze', [AIAnalysisController::class, 'sensexAnalyze'])->name('sensex.ai.analyze');
        Route::post('/sensex-chat', [AIAnalysisController::class, 'sensexChat'])->name('sensex.chat');
        Route::post('/sensex-chart-chat', [AIAnalysisController::class, 'sensexChartChat'])->name('sensex.chart.chat');

        // --- DEBUG & UTILITIES ---
        Route::get('/sensex-debug-tokens', [SensexOptionDataController::class, 'debugTokens'])->name('sensex.debug.tokens');
        Route::get('/sensex-debug', [SensexOptionDataController::class, 'debug'])->name('sensex.debug');
        
        Route::get('/sensex-fix-spot', function () {
            Cache::put('sensex_spot_last', 78918.9, now()->addHours(8));
            return response()->json(['done' => true, 'spot_set' => 78918.9]);
        });

        // --- SEARCH ---
        Route::get('/search', [SearchController::class, 'index'])->name('search.index');
        // Search stock API
        Route::get('/search-stock', [SearchController::class, 'search'])
            ->name('angel.search.stock');
    });
});

/*
|--------------------------------------------------------------------------
| External Debugging (Outside Middleware if needed)
|--------------------------------------------------------------------------
*/
Route::get('/angel/sensex-debug-now', [SensexOptionDataController::class, 'debugNow']);