<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NiftyOptionChainService;
use App\Services\HistoricalCandleService;

class NiftyOptionDataController extends Controller
{
    public function __construct(
        private NiftyOptionChainService $optionChainService,
        private HistoricalCandleService $candleService
    ) {
    }

    public function index(Request $request)
    {
        return $this->optionChainService->index($request);
    }

    public function refreshChainData(Request $request)
    {
        return $this->optionChainService->refresh($request);
    }

    public function getCandleData(Request $request)
    {
        return $this->candleService->getCandles($request);
    }
}