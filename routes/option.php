<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AI\OptionAnalysisController;

Route::post(
    '/angel/option-ai-analyze',
    [OptionAnalysisController::class, 'analyze']
);