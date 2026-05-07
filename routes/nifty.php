<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AI\NiftyAnalysisController;
use App\Http\Controllers\Market\NiftyHistoryController;
use App\Http\Controllers\Market\NiftyLiveController;
use App\Http\Controllers\Nifty\NiftyDashboardController;
use App\Http\Controllers\Nifty\NiftyOptionChainController;
use App\Http\Controllers\Nifty\NiftyChartController;
use App\Http\Controllers\Nifty\NiftyExpiryController;

Route::post(
    '/angel/nifty-ai-analyze',
    [NiftyAnalysisController::class, 'analyze']
);

Route::post(
    '/angel/nifty-ai-chat',
    [NiftyAnalysisController::class, 'chat']
);

Route::get(
    '/angel/dashboard',
    [NiftyHistoryController::class, 'index']
);

Route::get(
    '/angel/nifty-live',
    [NiftyHistoryController::class, 'live']
);

Route::get(
    '/angel/nifty-history',
    [NiftyHistoryController::class, 'history']
);

Route::get(
    '/angel/nifty-live',
    [NiftyLiveController::class, 'index']
);

Route::get(
    '/nifty',
    [NiftyDashboardController::class, 'index']
);

Route::get(
    '/nifty/option-chain',
    [NiftyOptionChainController::class, 'index']
);

Route::post(
    '/nifty/chart',
    [NiftyChartController::class, 'index']
);

Route::get(
    '/nifty/change-expiry',
    [NiftyExpiryController::class, 'change']
);