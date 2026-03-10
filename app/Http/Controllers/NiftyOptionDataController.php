<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NiftyOptionDataController extends Controller
{
    private $baseUrl = "https://apiconnect.angelone.in";

    // ─── Main Option Chain Page ────────────────────────────────────────────────
    public function index(Request $request)
    {
        set_time_limit(120);
        ini_set('default_socket_timeout', 120);

        try {
            $token = session('angel_jwt');
            if (!$token) {
                return redirect()->route('angel.login')->with('error', 'Please login first.');
            }

            $allExpiries    = $this->getAvailableExpiries();
            $selectedExpiry = $request->get('expiry', $allExpiries[0] ?? '13MAR2026');
            $marketStatus   = $this->getMarketStatus();

            // ── Nifty Spot ────────────────────────────────────────────────────
            $niftySpot = $this->getNiftySpotPrice($token);
            if (!$niftySpot) {
                $niftySpot = Cache::get('last_nifty_spot', 22500.00);
            } else {
                Cache::put('last_nifty_spot', $niftySpot, 300);
            }

            // ── Strikes & Symbols ─────────────────────────────────────────────
            $strikePrices  = $this->generateStrikePrices($niftySpot);
            $optionSymbols = $this->buildOptionSymbols($strikePrices, $selectedExpiry);

            // ── Scrip Master → Token map ───────────────────────────────────────
            $symbolTokenMap = $this->getSymbolTokenMap();
            $allTokens      = [];
            $tokenToSymbol  = [];

            foreach ($optionSymbols as $sym) {
                if (isset($symbolTokenMap[$sym])) {
                    $tok                 = $symbolTokenMap[$sym];
                    $allTokens[]         = $tok;
                    $tokenToSymbol[$tok] = $sym;
                }
            }

            // ── Fetch in chunks of 50 (Angel One limit) ───────────────────────
            $marketData = [];
            $chunks     = array_chunk($allTokens, 50);

            foreach ($chunks as $chunk) {
                try {
                    $response = Http::timeout(15)->withHeaders($this->getHeaders($token))
                        ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", [
                            'mode'           => 'FULL',
                            'exchangeTokens' => ['NFO' => $chunk],
                        ]);

                    $apiRes = $response->json();
                    if (!empty($apiRes['data']['fetched'])) {
                        $marketData = array_merge($marketData, $apiRes['data']['fetched']);
                    }

                    // Rate limit avoid karo
                    if (count($chunks) > 1) usleep(350000);

                } catch (\Exception $e) {
                    Log::warning('Quote chunk failed: ' . $e->getMessage());
                    continue;
                }
            }

            // Cache karo agar data mila
            if (!empty($marketData)) {
                Cache::put("nifty_chain_{$selectedExpiry}_cache", $marketData, 300);
            } else {
                $marketData = Cache::get("nifty_chain_{$selectedExpiry}_cache", []);
            }

            // ── Greeks ────────────────────────────────────────────────────────
            $greekExpiry = Carbon::createFromFormat('dMY', $selectedExpiry, 'Asia/Kolkata')->format('d-M-Y');
            $greeksData  = $this->getOptionGreeks($token, "NIFTY", $greekExpiry);

            // ── Merge ─────────────────────────────────────────────────────────
            $optionsData = $this->mergeData($marketData, $greeksData, $strikePrices);

            return view('nifty.option-data', [
                'optionsData'    => $optionsData,
                'niftySpot'      => $niftySpot,
                'selectedExpiry' => $selectedExpiry,
                'allExpiries'    => $allExpiries,
                'marketStatus'   => $marketStatus,
            ]);

        } catch (\Exception $e) {
            Log::error('Option Chain Error: ' . $e->getMessage());
            return view('nifty.option-data', [
                'optionsData'    => [],
                'niftySpot'      => Cache::get('last_nifty_spot', 22500),
                'selectedExpiry' => '',
                'allExpiries'    => [],
                'marketStatus'   => $this->getMarketStatus(),
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // ─── Refresh Options Data (AJAX) — angel/option-data/refresh ──────────────
    // ✅ FIX: Yeh method missing tha — add kiya
    public function refreshOptionsData(Request $request)
    {
        return $this->refreshChainData($request);
    }

    // ─── Refresh Chain Data (AJAX) — angel/chain-refresh ─────────────────────
    public function refreshChainData(Request $request)
    {
        try {
            $token          = session('angel_jwt');
            $selectedExpiry = $request->get('expiry', '');

            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Not logged in']);
            }

            // ── Spot ──────────────────────────────────────────────────────────
            $niftySpot = $this->getNiftySpotPrice($token);
            if ($niftySpot) {
                Cache::put('last_nifty_spot', $niftySpot, 300);
            } else {
                $niftySpot = Cache::get('last_nifty_spot', 22500);
            }

            // ── Tokens in chunks ──────────────────────────────────────────────
            $strikePrices   = $this->generateStrikePrices($niftySpot);
            $optionSymbols  = $this->buildOptionSymbols($strikePrices, $selectedExpiry);
            $symbolTokenMap = $this->getSymbolTokenMap();

            $allTokens = [];
            foreach ($optionSymbols as $sym) {
                if (isset($symbolTokenMap[$sym])) {
                    $allTokens[] = $symbolTokenMap[$sym];
                }
            }

            $marketData = [];
            $chunks     = array_chunk($allTokens, 50);

            foreach ($chunks as $chunk) {
                try {
                    $response = Http::timeout(10)
                        ->withHeaders($this->getHeaders($token))
                        ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", [
                            'mode'           => 'FULL',
                            'exchangeTokens' => ['NFO' => $chunk],
                        ]);
                    $apiRes = $response->json();
                    if (!empty($apiRes['data']['fetched'])) {
                        $marketData = array_merge($marketData, $apiRes['data']['fetched']);
                    }
                    if (count($chunks) > 1) usleep(350000);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // ── Format result ─────────────────────────────────────────────────
            $result = [];
            foreach ($marketData as $item) {
                if (preg_match('/NIFTY(\d{2})([A-Z]{3})(\d{2})(\d+)(CE|PE)$/', $item['tradingSymbol'], $m)) {
                    $strike = (int)$m[4];
                    $type   = strtolower($m[5]);
                    $result[$strike][$type] = [
                        'ltp'           => $item['ltp']          ?? 0,
                        'oi'            => $item['opnInterest']   ?? 0,
                        'percentChange' => $item['percentChange'] ?? 0,
                        'oiChange'      => $item['oiChange']      ?? 0,
                    ];
                }
            }

            // ATM calculate karo
            $atm = (int)(round($niftySpot / 50) * 50);

            return response()->json([
                'success'   => true,
                'niftySpot' => $niftySpot,
                'atm'       => $atm,
                'data'      => $result,
                'time'      => now('Asia/Kolkata')->format('H:i:s'),
            ]);

        } catch (\Exception $e) {
            Log::error('refreshChainData: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ─── Historical Candle Data (AJAX) ────────────────────────────────────────
    public function getCandleData(Request $request)
    {
        set_time_limit(60);

        try {
            $token       = session('angel_jwt');
            $symbolToken = $request->get('token');
            $exchange    = $request->get('exchange', 'NFO');
            $interval    = $request->get('interval', 'FIVE_MINUTE');
            $expiryParam = $request->get('expiry', '');

            if (!$token || !$symbolToken) {
                return response()->json(['success' => false, 'message' => 'Missing params', 'data' => []]);
            }

            $now     = Carbon::now('Asia/Kolkata');
            $nowHHMM = (int)$now->format('Hi');

            // ── Expiry parse ──────────────────────────────────────────────────
            $expiryCarbon = null;
            if (!empty($expiryParam)) {
                try {
                    $expiryCarbon = Carbon::createFromFormat('dMY', $expiryParam, 'Asia/Kolkata');
                } catch (\Exception $e) {
                    try {
                        $expiryCarbon = Carbon::parse($expiryParam, 'Asia/Kolkata');
                    } catch (\Exception $e2) {
                        $expiryCarbon = null;
                    }
                }
            }

            // ── toDate & isLive ───────────────────────────────────────────────
            $isLive         = false;
            $toDate         = null;
            $lastTradingDay = $this->getLastTradingDay($now);

            if ($expiryCarbon) {
                $expiryDateOnly = $expiryCarbon->copy()->startOfDay();
                $todayOnly      = $now->copy()->startOfDay();

                if ($expiryDateOnly->lt($todayOnly)) {
                    // Expired contract
                    $expiryLastDay = $this->getLastTradingDay($expiryCarbon);
                    $toDate        = $expiryLastDay->copy()->setTime(15, 30, 0)->format('Y-m-d H:i:s');
                    $isLive        = false;

                } elseif ($expiryDateOnly->eq($todayOnly)) {
                    // Expiry aaj
                    $isLive = ($nowHHMM >= 915 && $nowHHMM <= 1530);
                    $toDate = $isLive
                        ? $now->copy()->subMinute()->format('Y-m-d H:i:s')
                        : $now->copy()->setTime(15, 30, 0)->format('Y-m-d H:i:s');

                } else {
                    // Future expiry
                    $isLive = $lastTradingDay->isSameDay($now) && $nowHHMM >= 915 && $nowHHMM <= 1530;
                    $toDate = $isLive
                        ? $now->copy()->subMinute()->format('Y-m-d H:i:s')
                        : $lastTradingDay->copy()->setTime(15, 30, 0)->format('Y-m-d H:i:s');
                }
            } else {
                $isLive = $lastTradingDay->isSameDay($now) && $nowHHMM >= 915 && $nowHHMM <= 1530;
                $toDate = $isLive
                    ? $now->copy()->subMinute()->format('Y-m-d H:i:s')
                    : $lastTradingDay->copy()->setTime(15, 30, 0)->format('Y-m-d H:i:s');
            }

            // ── fromDate: HAMESHA toDate se peeche ───────────────────────────
            $daysBack = match($interval) {
                'ONE_MINUTE'      => 5,
                'THREE_MINUTE'    => 5,
                'FIVE_MINUTE'     => 5,
                'FIFTEEN_MINUTE'  => 10,
                'THIRTY_MINUTE'   => 20,
                'ONE_HOUR'        => 30,
                'ONE_DAY'         => 365,
                default           => 5,
            };

            $toCarbon = Carbon::parse($toDate, 'Asia/Kolkata');
            $fromDay  = $toCarbon->copy()->subDays($daysBack);
            while ($fromDay->isWeekend()) {
                $fromDay->subDay();
            }
            $fromDate = $fromDay->format('Y-m-d') . ' 09:15:00';

            // ── Cache ─────────────────────────────────────────────────────────
            $cacheDate = $isLive ? $now->format('YmdHi') : $toCarbon->format('Ymd');
            $cacheTtl  = $isLive ? 30 : 7200;
            $cacheKey  = "candle_v9_{$symbolToken}_{$interval}_{$cacheDate}";

            $data = Cache::remember($cacheKey, $cacheTtl, function () use (
                $token, $symbolToken, $exchange, $interval, $fromDate, $toDate
            ) {
                $response = Http::timeout(20)
                    ->withHeaders($this->getHeaders($token))
                    ->post("https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getCandleData", [
                        "exchange"    => $exchange,
                        "symboltoken" => $symbolToken,
                        "interval"    => $interval,
                        "fromdate"    => $fromDate,
                        "todate"      => $toDate,
                    ]);

                $json = $response->json();

                if (empty($json['data'])) {
                    Log::warning('getCandleData empty', [
                        'symbolToken' => $symbolToken,
                        'interval'    => $interval,
                        'fromdate'    => $fromDate,
                        'todate'      => $toDate,
                        'response'    => $json,
                    ]);
                }

                return $json['data'] ?? [];
            });

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => "No data — {$interval} | {$fromDate} → {$toDate}",
                    'data'    => [],
                ]);
            }

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('getCandleData: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
        }
    }

    // ─── Helper: Symbol → Token Map ───────────────────────────────────────────
    private function getSymbolTokenMap(): array
    {
        return Cache::remember('angel_nifty_token_map_v2', 3600, function () {
            $url    = "https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json";
            $scrips = json_decode(file_get_contents($url), true);

            $map = [];
            foreach ($scrips as $scrip) {
                if (($scrip['exch_seg'] ?? '') === 'NFO' && ($scrip['name'] ?? '') === 'NIFTY') {
                    $map[$scrip['symbol']] = $scrip['token'];
                }
            }
            return $map;
        });
    }

    // ─── Helper: Last Trading Day ─────────────────────────────────────────────
    private function getLastTradingDay(Carbon $now): Carbon
    {
        $day = $now->copy()->startOfDay();
        while ($day->isWeekend()) {
            $day->subDay();
        }
        if ($day->isSameDay($now) && $now->format('H:i') < '09:15') {
            $day->subDay();
            while ($day->isWeekend()) {
                $day->subDay();
            }
        }
        return $day;
    }

    // ─── Available Expiries ───────────────────────────────────────────────────
    private function getAvailableExpiries(): array
    {
        return Cache::remember('nifty_expiry_list_v4', 3600, function () {
            $url    = "https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json";
            $scrips = json_decode(file_get_contents($url), true);

            $expiries = [];
            $today    = strtotime(date('Y-m-d'));

            foreach ($scrips as $scrip) {
                if (
                    ($scrip['name']     ?? '') === 'NIFTY' &&
                    ($scrip['exch_seg'] ?? '') === 'NFO'   &&
                    !empty($scrip['expiry'])
                ) {
                    if (strtotime($scrip['expiry']) >= $today) {
                        $expiries[] = $scrip['expiry'];
                    }
                }
            }

            $unique = array_values(array_unique($expiries));
            usort($unique, fn($a, $b) => strtotime($a) - strtotime($b));
            return array_slice($unique, 0, 10);
        });
    }

    // ─── Merge Market Data + Greeks ───────────────────────────────────────────
    private function mergeData(array $marketData, array $greeksData, array $strikePrices): array
    {
        $chain = [];
        foreach ($strikePrices as $s) {
            $chain[$s] = [
                'strike' => $s,
                'ce'     => ['ltp' => 0, 'oi' => 0, 'percentChange' => 0, 'rho' => 0, 'vega' => 0, 'gamma' => 0, 'theta' => 0, 'delta' => 0, 'iv' => 0, 'tv_symbol' => null, 'symbol_token' => null],
                'pe'     => ['ltp' => 0, 'oi' => 0, 'percentChange' => 0, 'rho' => 0, 'vega' => 0, 'gamma' => 0, 'theta' => 0, 'delta' => 0, 'iv' => 0, 'tv_symbol' => null, 'symbol_token' => null],
            ];
        }

        $months = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
        ];

        foreach ($marketData as $item) {
            if (!preg_match('/NIFTY(\d{2})([A-Z]{3})(\d{2})(\d+)(CE|PE)$/', $item['tradingSymbol'] ?? '', $m)) continue;

            $strikeKey = (int)$m[4];
            if (!isset($chain[$strikeKey])) continue;

            $t         = strtolower($m[5]);
            $monthNum  = $months[$m[2]] ?? '01';
            $tvSymbol  = "NIFTY{$m[3]}{$monthNum}{$m[1]}" . ($t === 'ce' ? 'C' : 'P') . $m[4];

            $chain[$strikeKey][$t]['ltp']           = $item['ltp']          ?? 0;
            $chain[$strikeKey][$t]['oi']            = $item['opnInterest']   ?? 0;
            $chain[$strikeKey][$t]['percentChange'] = $item['percentChange'] ?? 0;
            $chain[$strikeKey][$t]['tv_symbol']     = $tvSymbol;
            $chain[$strikeKey][$t]['symbol_token']  = $item['symbolToken']   ?? null;
        }

        foreach ($greeksData as $greek) {
            $strike = (int)($greek['strikePrice'] ?? 0);
            $type   = strtolower($greek['optionType'] ?? '');
            if ($strike > 0 && isset($chain[$strike][$type])) {
                $chain[$strike][$type]['delta'] = $greek['delta']             ?? 0;
                $chain[$strike][$type]['theta'] = $greek['theta']             ?? 0;
                $chain[$strike][$type]['vega']  = $greek['vega']              ?? 0;
                $chain[$strike][$type]['gamma'] = $greek['gamma']             ?? 0;
                $chain[$strike][$type]['iv']    = $greek['impliedVolatility'] ?? 0;
                $chain[$strike][$type]['rho']   = $greek['rho']               ?? 0;
            }
        }

        ksort($chain);
        return $chain;
    }

    // ─── Strike Price Generator ────────────────────────────────────────────────
    // ✅ FIX: 1000 range = 40 strikes × 2 = 80 tokens (fits in 2 chunks of 50)
    private function generateStrikePrices($spot): array
    {
        $atm = (int)(round($spot / 50) * 50);
        return range($atm - 1000, $atm + 1000, 50);
    }

    // ─── Build Symbol List ────────────────────────────────────────────────────
    private function buildOptionSymbols(array $strikes, string $expiry): array
    {
        $symbols         = [];
        $formattedExpiry = strtoupper($expiry);

        // 2026 → 26 (Angel One 2-digit year format)
        if (strlen($formattedExpiry) > 7) {
            $formattedExpiry = substr($formattedExpiry, 0, 5) . substr($formattedExpiry, -2);
        }

        foreach ($strikes as $s) {
            $symbols[] = "NIFTY{$formattedExpiry}{$s}CE";
            $symbols[] = "NIFTY{$formattedExpiry}{$s}PE";
        }
        return $symbols;
    }

    // ─── Market Status ────────────────────────────────────────────────────────
    private function getMarketStatus(): array
    {
        $now          = Carbon::now('Asia/Kolkata');
        $isMarketOpen = $now->isWeekday() && $now->between('09:15', '15:30');
        return [
            'is_open'      => $isMarketOpen,
            'status'       => $isMarketOpen ? 'Market Open' : 'Market Closed',
            'current_time' => $now->format('d-M-Y H:i:s'),
            'message'      => $isMarketOpen ? 'Live Data' : 'Showing Last Close',
        ];
    }

    // ─── Nifty Spot Price ─────────────────────────────────────────────────────
    private function getNiftySpotPrice($token): ?float
    {
        try {
            $response = Http::timeout(10)->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", [
                    'mode'           => 'LTP',
                    'exchangeTokens' => ['NSE' => ['99926000']],
                ]);
            return $response->json()['data']['fetched'][0]['ltp'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ─── Option Greeks ────────────────────────────────────────────────────────
    private function getOptionGreeks($token, string $name, string $expiry): array
    {
        try {
            $response = Http::timeout(10)->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . "/rest/secure/angelbroking/marketData/v1/optionGreek", [
                    "name"       => $name,
                    "expirydate" => $expiry,
                ]);
            return $response->json()['data'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Common API Headers ───────────────────────────────────────────────────
    private function getHeaders($token): array
    {
        return [
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
            'X-UserType'       => 'USER',
            'X-SourceID'       => 'WEB',
            'X-PrivateKey'     => env('ANGEL_API_KEY'),
            'X-ClientLocalIP'  => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',
            'X-MACAddress'     => '00:00:00:00:00:00',
            'Authorization'    => 'Bearer ' . $token,
        ];
    }
}
