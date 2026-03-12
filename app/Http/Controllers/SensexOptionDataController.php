<?php

namespace App\Http\Controllers;

use App\Services\SensexOptionDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SensexOptionDataController extends Controller
{
    public function __construct(private SensexOptionDataService $sensexService)
    {
    }

    public function index(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return redirect()->route('angel.login');
        }

        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $selectedExpiry = $request->get('expiry', $allExpiries[0] ?? '');

        $spot = $this->sensexService->getSensexSpotPrice($token);

        if ($spot && $spot > 0) {
            Cache::put('sensex_spot_last', $spot, now()->addHours(6));
        } else {
            $spot = Cache::get('sensex_spot_last', 0);
        }

        $strikeMap = Cache::get("sensex_map_{$selectedExpiry}", []);
        $filteredMap = $this->sensexService->filterAtmStrikes($strikeMap, $spot, 15);
        $optionsData = $this->sensexService->fetchMarketDataInBulk($token, $filteredMap);

        return view('sensex.option-data', [
            'optionsData' => $optionsData,
            'sensexSpot' => $spot,
            'selectedExpiry' => $selectedExpiry,
            'allExpiries' => $allExpiries,
            'marketStatus' => ['is_open' => $this->sensexService->isMarketOpen()],
        ]);
    }

    public function debugTokens(Request $request)
    {
        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $expiry = $request->get('expiry', $allExpiries[0] ?? '12MAR2026');
        $strikeMap = Cache::get("sensex_map_{$expiry}", []);

        if (empty($strikeMap)) {
            return response()->json([
                'error' => 'No strike map in cache for: ' . $expiry,
                'tip' => 'Run: php artisan scrip:cache',
                'available_expiries' => $allExpiries,
            ], 200, [], JSON_PRETTY_PRINT);
        }

        $spot = Cache::get('sensex_spot_last', 0);
        $strikes = array_keys($strikeMap);
        sort($strikes);

        $atm = $strikes[0];
        $minDiff = PHP_INT_MAX;
        foreach ($strikes as $s) {
            $diff = abs($s - $spot);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $atm = $s;
            }
        }

        $sample = [];
        foreach ($strikes as $s) {
            if (abs($s - $atm) <= 500) {
                $sample[$s] = $strikeMap[$s];
            }
        }

        $atmRow = $strikeMap[$atm] ?? [];
        $atmCeToken = $atmRow['ce'] ?? null;
        $atmPeToken = $atmRow['pe'] ?? null;

        return response()->json([
            'expiry' => $expiry,
            'spot' => $spot,
            'atm_strike' => $atm,
            'total_strikes' => count($strikeMap),
            'next_step_test_urls' => [
                'atm_ce_candle' => $atmCeToken
                    ? "/angel/sensex-debug?token={$atmCeToken}&exchange=BFO&interval=FIVE_MINUTE"
                    : 'no CE token found',
                'atm_pe_candle' => $atmPeToken
                    ? "/angel/sensex-debug?token={$atmPeToken}&exchange=BFO&interval=FIVE_MINUTE"
                    : 'no PE token found',
            ],
            'sample_strikes_near_atm' => $sample,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function debug(Request $request)
    {
        $token = session('angel_jwt');
        $symbolToken = trim($request->get('token', ''));
        $exchange = strtoupper($request->get('exchange', 'BFO'));
        $interval = $request->get('interval', 'FIVE_MINUTE');

        if (!$token) {
            return response()->json(['error' => 'Session expired — please login again'], 401);
        }

        $now = Carbon::now('Asia/Kolkata');
        $lastTrading = $this->sensexService->lastTradingDay($now);
        $fromDateStr = $lastTrading->copy()->subDays(5)->format('Y-m-d') . ' 09:15';
        $toDateStr = $lastTrading->format('Y-m-d') . ' 15:30';

        $spotBody = $this->sensexService->fetchLtpQuote($token, 'BSE', '99919000');

        $candleBody = null;
        if ($symbolToken) {
            $candleBody = $this->sensexService->fetchCandleData(
                $token,
                $exchange,
                $symbolToken,
                $interval,
                $fromDateStr,
                $toDateStr
            );

            if (!empty($candleBody['data'])) {
                $candleBody['_summary_count'] = count($candleBody['data']);
                $candleBody['_first_candle'] = $candleBody['data'][0];
                $candleBody['_last_candle'] = end($candleBody['data']);
                unset($candleBody['data']);
            }
        }

        $quoteBody = null;
        if ($symbolToken) {
            $quoteBody = $this->sensexService->fetchLtpQuote($token, $exchange, $symbolToken);
        }

        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $firstExpiry = $allExpiries[0] ?? null;
        $strikeMap = $firstExpiry ? Cache::get("sensex_map_{$firstExpiry}", []) : [];

        return response()->json([
            'ist_now' => $now->format('Y-m-d H:i:s'),
            'last_trading_day' => $lastTrading->format('Y-m-d'),
            'is_market_open' => $this->sensexService->isMarketOpen(),
            'session_token_ok' => !empty($token),
            'session_token_length' => strlen($token),
            'candle_request_sent' => [
                'exchange' => $exchange,
                'symboltoken' => $symbolToken ?: '(not provided)',
                'interval' => $interval,
                'fromdate' => $fromDateStr,
                'todate' => $toDateStr,
            ],
            'result_1_sensex_spot' => $spotBody,
            'result_2_candle_data' => $candleBody ?? '(provide ?token=XXXXX in URL)',
            'result_3_ltp_quote' => $quoteBody ?? '(provide ?token=XXXXX in URL)',
            'cache_expiry_list' => $allExpiries,
            'cache_strike_map_count_first_expiry' => count($strikeMap),
            'cache_spot_last' => Cache::get('sensex_spot_last'),
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function getCandleData(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Session expired.']);
        }

        $interval = $request->get('interval', 'FIVE_MINUTE');
        $exchange = strtoupper($request->get('exchange', 'BFO'));
        $symbolToken = trim($request->get('token', ''));

        if (!$symbolToken) {
            return response()->json(['success' => false, 'message' => 'Symbol token missing.']);
        }

        $now = Carbon::now('Asia/Kolkata');
        $lastTrading = $this->sensexService->lastTradingDay($now);

        if ($this->sensexService->isMarketOpen()) {
            $toDateStr = $now->format('Y-m-d H:i');
        } else {
            $toDateStr = $lastTrading->format('Y-m-d') . ' 15:30';
        }

        $daysBack = match ($interval) {
            'ONE_MINUTE' => 1,
            'THREE_MINUTE' => 5,
            'FIVE_MINUTE' => 5,
            'FIFTEEN_MINUTE' => 20,
            'THIRTY_MINUTE' => 30,
            'ONE_HOUR' => 60,
            default => 5,
        };

        $fromDate = $lastTrading->copy();
        $daysSkipped = 0;
        while ($daysSkipped < $daysBack) {
            $fromDate->subDay();
            if ($fromDate->isWeekday()) {
                $daysSkipped++;
            }
        }
        $fromDateStr = $fromDate->format('Y-m-d') . ' 09:15';

        $fromAttempts = [
            $fromDateStr,
            $lastTrading->copy()->subDays(3)->format('Y-m-d') . ' 09:15',
            $lastTrading->copy()->subDays(1)->format('Y-m-d') . ' 09:15',
        ];

        $lastError = '';
        $lastApiMsg = '';

        foreach ($fromAttempts as $fromAttempt) {
            $body = $this->sensexService->fetchCandleData(
                $token,
                $exchange,
                $symbolToken,
                $interval,
                $fromAttempt,
                $toDateStr
            );

            $errorcode = $body['errorcode'] ?? $body['errorCode'] ?? '';
            $apiMsg = $body['message'] ?? '';

            if (!empty($errorcode) && $errorcode !== '0') {
                $lastError = "Angel error [{$errorcode}]: {$apiMsg}";
                $lastApiMsg = $apiMsg;
                continue;
            }

            if (!empty($body['data']) && is_array($body['data'])) {
                return response()->json([
                    'success' => true,
                    'data' => $body['data'],
                    'count' => count($body['data']),
                ]);
            }

            $lastError = "Empty data | msg: {$apiMsg}";
            $lastApiMsg = $apiMsg;
        }

        return response()->json([
            'success' => false,
            'message' => $lastApiMsg ?: $lastError ?: 'No candle data available.',
            'debug' => [
                'token' => $symbolToken,
                'exchange' => $exchange,
                'interval' => $interval,
                'todate' => $toDateStr,
                'last_trading' => $lastTrading->format('Y-m-d'),
            ],
            'data' => [],
        ]);
    }

    public function getLiveTick(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['success' => false]);
        }

        $symbolToken = trim($request->get('token', ''));
        $exchange = strtoupper($request->get('exchange', 'BFO'));

        if (!$symbolToken) {
            return response()->json(['success' => false, 'message' => 'Token missing']);
        }

        $body = $this->sensexService->fetchLtpQuote($token, $exchange, $symbolToken);
        $tick = $body['data']['fetched'][0] ?? null;
        $unfetch = $body['data']['unfetched'][0] ?? null;

        return response()->json([
            'success' => (bool) $tick,
            'tick' => $tick,
            'unfetched' => $unfetch,
            'message' => $body['exception'] ?? null,
        ]);
    }

    public function chainRefresh(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['success' => false]);
        }

        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $expiry = $request->get('expiry', $allExpiries[0] ?? '');

        $spot = $this->sensexService->getSensexSpotPrice($token);
        if ($spot && $spot > 0) {
            Cache::put('sensex_spot_last', $spot, now()->addHours(6));
        } else {
            $spot = Cache::get('sensex_spot_last', 0);
        }

        $strikeMap = Cache::get("sensex_map_{$expiry}", []);
        $filtered = $this->sensexService->filterAtmStrikes($strikeMap, $spot, 15);
        $options = $this->sensexService->fetchMarketDataInBulk($token, $filtered);

        return response()->json([
            'success' => true,
            'sensexSpot' => $spot,
            'data' => $options,
            'time' => now('Asia/Kolkata')->format('H:i:s'),
        ]);
    }
}
