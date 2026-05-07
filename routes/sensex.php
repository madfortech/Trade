<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Sensex\SensexOptionChainController;
use App\Http\Controllers\Sensex\SensexChartController;

use App\Http\Controllers\AI\SensexAnalysisController;

/*
|--------------------------------------------------------------------------
| OPTION CHAIN
|--------------------------------------------------------------------------
*/

Route::get(

    '/angel/sensex-option-chain',

    [SensexOptionChainController::class, 'index']

)->name('sensex.option-chain');

/*
|--------------------------------------------------------------------------
| AUTO REFRESH
|--------------------------------------------------------------------------
*/

Route::get(

    '/angel/sensex-chain-refresh',

    [SensexOptionChainController::class, 'refresh']

)->name('sensex.chain.refresh');

/*
|--------------------------------------------------------------------------
| CANDLE DATA
|--------------------------------------------------------------------------
*/

Route::get(

    '/angel/sensex-candle-data',

    [SensexChartController::class, 'index']

)->name('sensex.candle.data');

/*
|--------------------------------------------------------------------------
| AI ANALYSIS
|--------------------------------------------------------------------------
*/

Route::post(

    '/angel/sensex-ai-analyze',

    [SensexAnalysisController::class, 'analyze']

)->name('sensex.ai.analyze');

