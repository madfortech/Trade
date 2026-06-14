<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HistoricalCandleService
{
    public function __construct(
        private AngelOneApiService $angelApi
    ) {
    }

    public function getCandles(
        string $token,
        string $symbolToken,
        string $exchange,
        string $interval
    ): array {

        $now = Carbon::now('Asia/Kolkata');

        $targetDays = match ($interval) {
            'ONE_MINUTE'     => 120,
            'THREE_MINUTE'   => 300,
            'FIVE_MINUTE'    => 500,
            'FIFTEEN_MINUTE' => 1200,
            'THIRTY_MINUTE'  => 2000,
            'ONE_HOUR'       => 3500,
            default          => 500,
        };

        $fromDate = $now->copy()->subDays($targetDays);

        while ($fromDate->isWeekend()) {
            $fromDate->subDay();
        }

        try {

            $response = $this->angelApi->getHistoricalData(
                $token,
                $exchange,
                $symbolToken,
                $interval,
                $fromDate->format('Y-m-d 09:15'),
                $now->format('Y-m-d H:i')
            );

            $candles = $response['data'] ?? [];

            Log::info('Historical Candle Count', [
                'interval' => $interval,
                'count'    => count($candles),
                'token'    => $symbolToken,
            ]);

            return is_array($candles)
                ? $candles
                : [];

        } catch (\Throwable $e) {

            Log::error('Historical Candle Error', [
                'message' => $e->getMessage(),
                'token'   => $symbolToken,
            ]);

            return [];
        }
    }

    public function getLastTradingDay(): Carbon
    {
        $date = Carbon::now('Asia/Kolkata');

        while ($date->isWeekend()) {
            $date->subDay();
        }

        return $date;
    }

    public function isMarketOpen(): bool
    {
        $now = Carbon::now('Asia/Kolkata');

        $hhmm = (int) $now->format('Hi');

        return $now->isWeekday()
            && $hhmm >= 915
            && $hhmm <= 1530;
    }
}