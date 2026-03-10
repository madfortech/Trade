<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SensexOptionDataController extends Controller
{
    private string $baseUrl = 'https://apiconnect.angelone.in';

    // ───────────── MAIN PAGE ─────────────
    public function index(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return redirect()->route('angel.login');
        }

        $allExpiries    = Cache::get('sensex_expiry_list_v4', []);
        $selectedExpiry = $request->get('expiry', $allExpiries[0] ?? '');

        $spot = $this->getSensexSpotPrice($token);

        if ($spot && $spot > 0) {
            Cache::put('sensex_spot_last', $spot, now()->addHours(6));
        } else {
            $spot = Cache::get('sensex_spot_last', 0);
        }

        $strikeMap   = Cache::get("sensex_map_{$selectedExpiry}", []);
        $filteredMap = $this->filterAtmStrikes($strikeMap, $spot, 15);
        $optionsData = $this->fetchMarketDataInBulk($token, $filteredMap);

        return view('sensex.option-data', [
            'optionsData'    => $optionsData,
            'sensexSpot'     => $spot,
            'selectedExpiry' => $selectedExpiry,
            'allExpiries'    => $allExpiries,
            'marketStatus'   => ['is_open' => $this->isMarketOpen()],
        ]);
    }

    // ───────────────────────────────────────────────────────────────────────
    // DEBUG 1: Cache tokens dikhao
    // URL: /angel/sensex-debug-tokens
    // URL: /angel/sensex-debug-tokens?expiry=12MAR2026
    // ───────────────────────────────────────────────────────────────────────
    public function debugTokens(Request $request)
    {
        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $expiry      = $request->get('expiry', $allExpiries[0] ?? '12MAR2026');
        $strikeMap   = Cache::get("sensex_map_{$expiry}", []);

        if (empty($strikeMap)) {
            return response()->json([
                'error'              => 'No strike map in cache for: ' . $expiry,
                'tip'                => 'Run: php artisan scrip:cache',
                'available_expiries' => $allExpiries,
            ], 200, [], JSON_PRETTY_PRINT);
        }

        $spot    = Cache::get('sensex_spot_last', 0);
        $strikes = array_keys($strikeMap);
        sort($strikes);

        $atm     = $strikes[0];
        $minDiff = PHP_INT_MAX;
        foreach ($strikes as $s) {
            $diff = abs($s - $spot);
            if ($diff < $minDiff) { $minDiff = $diff; $atm = $s; }
        }

        $sample = [];
        foreach ($strikes as $s) {
            if (abs($s - $atm) <= 500) {
                $sample[$s] = $strikeMap[$s];
            }
        }

        $atmRow     = $strikeMap[$atm] ?? [];
        $atmCeToken = $atmRow['ce'] ?? null;
        $atmPeToken = $atmRow['pe'] ?? null;

        return response()->json([
            'expiry'        => $expiry,
            'spot'          => $spot,
            'atm_strike'    => $atm,
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

    // ───────────────────────────────────────────────────────────────────────
    // DEBUG 2: Full Angel API test
    // URL: /angel/sensex-debug
    // URL: /angel/sensex-debug?token=845796&exchange=BFO&interval=FIVE_MINUTE
    // ───────────────────────────────────────────────────────────────────────
    public function debug(Request $request)
    {
        $token       = session('angel_jwt');
        $symbolToken = trim($request->get('token', ''));
        $exchange    = strtoupper($request->get('exchange', 'BFO'));
        $interval    = $request->get('interval', 'FIVE_MINUTE');

        if (!$token) {
            return response()->json(['error' => 'Session expired — please login again'], 401);
        }

        $now         = Carbon::now('Asia/Kolkata');
        $lastTrading = $this->lastTradingDay($now);
        $fromDateStr = $lastTrading->copy()->subDays(5)->format('Y-m-d') . ' 09:15';
        $toDateStr   = $lastTrading->format('Y-m-d') . ' 15:30';

        // ── Test 1: Sensex Spot ──────────────────────────────────────
        try {
            $spotRes  = Http::timeout(10)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode'           => 'LTP',
                    'exchangeTokens' => ['BSE' => ['99919000']],
                ]);
            $spotBody = $spotRes->json();
        } catch (\Exception $e) {
            $spotBody = ['exception' => $e->getMessage()];
        }

        // ── Test 2: Candle Data ──────────────────────────────────────
        $candleBody = null;
        if ($symbolToken) {
            try {
                $candleRes  = Http::timeout(25)
                    ->withHeaders($this->getHeaders($token))
                    ->post($this->baseUrl . '/rest/secure/angelbroking/historical/v1/getCandleData', [
                        'exchange'    => $exchange,
                        'symboltoken' => $symbolToken,
                        'interval'    => $interval,
                        'fromdate'    => $fromDateStr,
                        'todate'      => $toDateStr,
                    ]);
                $candleBody = $candleRes->json();

                if (!empty($candleBody['data'])) {
                    $candleBody['_summary_count'] = count($candleBody['data']);
                    $candleBody['_first_candle']  = $candleBody['data'][0];
                    $candleBody['_last_candle']   = end($candleBody['data']);
                    unset($candleBody['data']);
                }
            } catch (\Exception $e) {
                $candleBody = ['exception' => $e->getMessage()];
            }
        }

        // ── Test 3: LTP Quote ────────────────────────────────────────
        $quoteBody = null;
        if ($symbolToken) {
            try {
                $quoteRes  = Http::timeout(10)
                    ->withHeaders($this->getHeaders($token))
                    ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                        'mode'           => 'LTP',
                        'exchangeTokens' => [$exchange => [$symbolToken]],
                    ]);
                $quoteBody = $quoteRes->json();
            } catch (\Exception $e) {
                $quoteBody = ['exception' => $e->getMessage()];
            }
        }

        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $firstExpiry = $allExpiries[0] ?? null;
        $strikeMap   = $firstExpiry ? Cache::get("sensex_map_{$firstExpiry}", []) : [];

        return response()->json([
            'ist_now'              => $now->format('Y-m-d H:i:s'),
            'last_trading_day'     => $lastTrading->format('Y-m-d'),
            'is_market_open'       => $this->isMarketOpen(),
            'session_token_ok'     => !empty($token),
            'session_token_length' => strlen($token),
            'candle_request_sent'  => [
                'exchange'    => $exchange,
                'symboltoken' => $symbolToken ?: '(not provided)',
                'interval'    => $interval,
                'fromdate'    => $fromDateStr,
                'todate'      => $toDateStr,
            ],
            'result_1_sensex_spot' => $spotBody,
            'result_2_candle_data' => $candleBody ?? '(provide ?token=XXXXX in URL)',
            'result_3_ltp_quote'   => $quoteBody  ?? '(provide ?token=XXXXX in URL)',
            'cache_expiry_list'    => $allExpiries,
            'cache_strike_map_count_first_expiry' => count($strikeMap),
            'cache_spot_last'      => Cache::get('sensex_spot_last'),
        ], 200, [], JSON_PRETTY_PRINT);
    }

    // ───────────── CANDLE DATA ─────────────
    public function getCandleData(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Session expired.']);
        }

        $interval    = $request->get('interval', 'FIVE_MINUTE');
        $exchange    = strtoupper($request->get('exchange', 'BFO'));
        $symbolToken = trim($request->get('token', ''));

        if (!$symbolToken) {
            return response()->json(['success' => false, 'message' => 'Symbol token missing.']);
        }

        $now         = Carbon::now('Asia/Kolkata');
        $lastTrading = $this->lastTradingDay($now);

        // ✅ FIX: Always use last trading day as toDate — never a weekend/holiday
        // Market open hone par current time, warna last trading day 15:30
        if ($this->isMarketOpen()) {
            $toDateStr = $now->format('Y-m-d H:i');
        } else {
            $toDateStr = $lastTrading->format('Y-m-d') . ' 15:30';
        }

        // ✅ FIX: fromDate bhi last trading day se calculate karo, aaj se nahi
        $daysBack = match($interval) {
            'ONE_MINUTE'      => 1,
            'THREE_MINUTE'    => 5,
            'FIVE_MINUTE'     => 5,
            'FIFTEEN_MINUTE'  => 20,
            'THIRTY_MINUTE'   => 30,
            'ONE_HOUR'        => 60,
            default           => 5,
        };

        // Last trading day se peeche jaao, weekends skip karte hue
        $fromDate = $lastTrading->copy();
        $daysSkipped = 0;
        while ($daysSkipped < $daysBack) {
            $fromDate->subDay();
            if ($fromDate->isWeekday()) {
                $daysSkipped++;
            }
        }
        $fromDateStr = $fromDate->format('Y-m-d') . ' 09:15';

        // Fallback: agar data nahi mila toh progressively shorter range try karo
        $fromAttempts = [
            $fromDateStr,
            $lastTrading->copy()->subDays(3)->format('Y-m-d') . ' 09:15',
            $lastTrading->copy()->subDays(1)->format('Y-m-d') . ' 09:15',
        ];

        $lastError  = '';
        $lastApiMsg = '';

        foreach ($fromAttempts as $fromAttempt) {
            try {
                $res       = Http::timeout(25)
                    ->withHeaders($this->getHeaders($token))
                    ->post($this->baseUrl . '/rest/secure/angelbroking/historical/v1/getCandleData', [
                        'exchange'    => $exchange,
                        'symboltoken' => $symbolToken,
                        'interval'    => $interval,
                        'fromdate'    => $fromAttempt,
                        'todate'      => $toDateStr,
                    ]);

                $body      = $res->json();
                $errorcode = $body['errorcode'] ?? $body['errorCode'] ?? '';
                $apiMsg    = $body['message'] ?? '';

                if (!empty($errorcode) && $errorcode !== '0') {
                    $lastError  = "Angel error [{$errorcode}]: {$apiMsg}";
                    $lastApiMsg = $apiMsg;
                    continue;
                }

                if (!empty($body['data']) && is_array($body['data'])) {
                    return response()->json([
                        'success' => true,
                        'data'    => $body['data'],
                        'count'   => count($body['data']),
                    ]);
                }

                $lastError  = "Empty data | HTTP: {$res->status()} | msg: {$apiMsg}";
                $lastApiMsg = $apiMsg;

            } catch (\Exception $e) {
                $lastError = 'Exception: ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => false,
            'message' => $lastApiMsg ?: $lastError ?: 'No candle data available.',
            'debug'   => [
                'token'       => $symbolToken,
                'exchange'    => $exchange,
                'interval'    => $interval,
                'todate'      => $toDateStr,
                'last_trading'=> $lastTrading->format('Y-m-d'),
            ],
            'data'    => [],
        ]);
    }

    // ───────────── LIVE TICK ─────────────
    public function getLiveTick(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['success' => false]);
        }

        $symbolToken = trim($request->get('token', ''));
        $exchange    = strtoupper($request->get('exchange', 'BFO'));

        if (!$symbolToken) {
            return response()->json(['success' => false, 'message' => 'Token missing']);
        }

        try {
            $res     = Http::timeout(5)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode'           => 'LTP',
                    'exchangeTokens' => [$exchange => [$symbolToken]],
                ]);
            $body    = $res->json();
            $tick    = $body['data']['fetched'][0]   ?? null;
            $unfetch = $body['data']['unfetched'][0] ?? null;

            return response()->json([
                'success'   => !!$tick,
                'tick'      => $tick,
                'unfetched' => $unfetch,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ───────────── CHAIN REFRESH ─────────────
    public function chainRefresh(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['success' => false]);
        }

        $allExpiries = Cache::get('sensex_expiry_list_v4', []);
        $expiry      = $request->get('expiry', $allExpiries[0] ?? '');

        $spot = $this->getSensexSpotPrice($token);
        if ($spot && $spot > 0) {
            Cache::put('sensex_spot_last', $spot, now()->addHours(6));
        } else {
            $spot = Cache::get('sensex_spot_last', 0);
        }

        $strikeMap = Cache::get("sensex_map_{$expiry}", []);
        $filtered  = $this->filterAtmStrikes($strikeMap, $spot, 15);
        $options   = $this->fetchMarketDataInBulk($token, $filtered);

        return response()->json([
            'success'    => true,
            'sensexSpot' => $spot,
            'data'       => $options,
            'time'       => now('Asia/Kolkata')->format('H:i:s'),
        ]);
    }

    // ───────────── LAST TRADING DAY ─────────────
    // ✅ Weekend/Sunday pe bhi sahi kaam karta hai
    // Agar market abhi open hai → aaj return karo
    // Agar market band hai → pichla trading day (Mon-Fri) return karo
    private function lastTradingDay(Carbon $now): Carbon
    {
        $day = $now->copy()->setTimezone('Asia/Kolkata');

        // Agar aaj weekday hai aur market time ke baad bhi aaj ki date valid hai
        if ($day->isWeekday()) {
            return $day;
        }

        // Weekend hai — pichle Friday pe jao
        while ($day->isWeekend()) {
            $day->subDay();
        }

        return $day;
    }

    // ───────────── MARKET HOURS ─────────────
    private function isMarketOpen(): bool
    {
        $now  = Carbon::now('Asia/Kolkata');
        $hhmm = (int)$now->format('G') * 100 + (int)$now->format('i');
        return $now->isWeekday() && $hhmm >= 915 && $hhmm <= 1530;
    }

    // ───────────── STRIKE FILTER ─────────────
    private function filterAtmStrikes($map, $spot, $range)
    {
        if (empty($map)) return [];

        $strikes = array_keys($map);
        sort($strikes);

        $closestIdx = 0;
        $minDiff    = PHP_INT_MAX;

        foreach ($strikes as $i => $s) {
            $diff = abs($s - $spot);
            if ($diff < $minDiff) { $minDiff = $diff; $closestIdx = $i; }
        }

        $selected = array_slice($strikes, max(0, $closestIdx - $range), ($range * 2) + 1);

        $final = [];
        foreach ($selected as $s) {
            $final[$s] = $map[$s];
        }

        return $final;
    }

    // ───────────── OPTION DATA BULK ─────────────
    private function fetchMarketDataInBulk($token, $map)
    {
        if (empty($map)) return [];

        $tokens = [];
        foreach ($map as $row) {
            if (!empty($row['ce'])) $tokens[] = $row['ce'];
            if (!empty($row['pe'])) $tokens[] = $row['pe'];
        }

        try {
            $res  = Http::timeout(30)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode'           => 'FULL',
                    'exchangeTokens' => ['BFO' => $tokens],
                ]);
            $raw  = $res->json();
            $data = collect($raw['data']['fetched'] ?? [])
                ->keyBy(fn($i) => $i['symbolToken'] ?? $i['symboltoken'] ?? '');
        } catch (\Exception $e) {
            $data = collect([]);
        }

        $out = [];
        foreach ($map as $strike => $t) {
            $ce = $data[$t['ce'] ?? ''] ?? [];
            $pe = $data[$t['pe'] ?? ''] ?? [];

            $out[$strike] = [
                'ce' => [
                    'ltp'           => $ce['ltp'] ?? 0,
                    'oi'            => $ce['opnInterest'] ?? 0,
                    'percentChange' => $ce['percentChange'] ?? 0,
                    'symbol_token'  => $t['ce'] ?? null,
                ],
                'pe' => [
                    'ltp'           => $pe['ltp'] ?? 0,
                    'oi'            => $pe['opnInterest'] ?? 0,
                    'percentChange' => $pe['percentChange'] ?? 0,
                    'symbol_token'  => $t['pe'] ?? null,
                ],
            ];
        }

        return $out;
    }

    // ───────────── SENSEX SPOT ─────────────
    private function getSensexSpotPrice($token)
    {
        try {
            $res = Http::timeout(10)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode'           => 'LTP',
                    'exchangeTokens' => ['BSE' => ['99919000']],
                ]);
            return $res->json()['data']['fetched'][0]['ltp'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ───────────── HEADERS ─────────────
    private function getHeaders($token): array
    {
        return [
            'Content-Type'     => 'application/json',
            'Authorization'    => 'Bearer ' . $token,
            'X-PrivateKey'     => env('ANGEL_API_KEY'),
            'X-UserType'       => 'USER',
            'X-SourceID'       => 'WEB',
            'X-ClientLocalIP'  => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',
            'X-MACAddress'     => '00:00:00:00:00:00',
            'Accept'           => 'application/json',
        ];
    }
}
