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

            $niftySpot = $this->getNiftySpotPrice($token);
            if (!$niftySpot || $niftySpot <= 0) {
                $niftySpot = Cache::get('last_nifty_spot', 22500.00);
            } else {
                Cache::put('last_nifty_spot', $niftySpot, 300);
            }

            $strikePrices  = $this->generateStrikePrices($niftySpot);
            $optionSymbols = $this->buildOptionSymbols($strikePrices, $selectedExpiry);
            $symbolTokenMap = $this->getSymbolTokenMap();

            $allTokens     = [];
            $tokenToSymbol = [];
            foreach ($optionSymbols as $sym) {
                if (isset($symbolTokenMap[$sym])) {
                    $tok                 = $symbolTokenMap[$sym];
                    $allTokens[]         = $tok;
                    $tokenToSymbol[$tok] = $sym;
                }
            }

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
                    if (!empty($apiRes['data']['fetched']) && is_array($apiRes['data']['fetched'])) {
                        $marketData = array_merge($marketData, $apiRes['data']['fetched']);
                    }
                    if (count($chunks) > 1) usleep(350000);
                } catch (\Exception $e) {
                    Log::warning('Quote chunk failed: ' . $e->getMessage());
                }
            }

            if (!empty($marketData)) {
                Cache::put("nifty_chain_{$selectedExpiry}_cache", $marketData, 300);
            } else {
                $marketData = Cache::get("nifty_chain_{$selectedExpiry}_cache", []);
            }

            $greekExpiry = Carbon::createFromFormat('dMY', $selectedExpiry, 'Asia/Kolkata')->format('d-M-Y');
            $greeksData  = $this->getOptionGreeks($token, "NIFTY", $greekExpiry);
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

    public function refreshOptionsData(Request $request) { return $this->refreshChainData($request); }

    // ─── Refresh Chain Data (AJAX) ────────────────────────────────────────────
    public function refreshChainData(Request $request)
    {
        try {
            $token          = session('angel_jwt');
            $selectedExpiry = $request->get('expiry', '');
            if (!$token) return response()->json(['success' => false, 'message' => 'Not logged in']);

            $niftySpot = $this->getNiftySpotPrice($token);
            if ($niftySpot && $niftySpot > 0) {
                Cache::put('last_nifty_spot', $niftySpot, 300);
            } else {
                $niftySpot = Cache::get('last_nifty_spot', 22500);
            }

            $strikePrices   = $this->generateStrikePrices($niftySpot);
            $optionSymbols  = $this->buildOptionSymbols($strikePrices, $selectedExpiry);
            $symbolTokenMap = $this->getSymbolTokenMap();

            $allTokens = [];
            foreach ($optionSymbols as $sym) {
                if (isset($symbolTokenMap[$sym])) $allTokens[] = $symbolTokenMap[$sym];
            }

            $marketData = [];
            foreach (array_chunk($allTokens, 50) as $chunk) {
                try {
                    $r = Http::timeout(10)->withHeaders($this->getHeaders($token))
                        ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", [
                            'mode' => 'FULL', 'exchangeTokens' => ['NFO' => $chunk],
                        ]);
                    $ar = $r->json();
                    if (!empty($ar['data']['fetched']) && is_array($ar['data']['fetched'])) {
                        $marketData = array_merge($marketData, $ar['data']['fetched']);
                    }
                } catch (\Exception $e) { continue; }
            }

            $result = [];
            foreach ($marketData as $item) {
                if (!is_array($item)) continue;
                $parsed = $this->parseNiftySymbol($item['tradingSymbol'] ?? '');
                if (!$parsed) continue;
                $strike                 = $parsed['strike'];
                $type                   = $parsed['type'];
                $result[$strike][$type] = [
                    'ltp'           => $item['ltp']          ?? 0,
                    'oi'            => $item['opnInterest']   ?? 0,
                    'percentChange' => $item['percentChange'] ?? 0,
                    'oiChange'      => $item['oiChange']      ?? 0,
                ];
            }

            return response()->json([
                'success'   => true,
                'niftySpot' => $niftySpot,
                'atm'       => (int)(round($niftySpot / 50) * 50),
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

            $expiryCarbon = null;
            if (!empty($expiryParam)) {
                try { $expiryCarbon = Carbon::createFromFormat('dMY', $expiryParam, 'Asia/Kolkata'); }
                catch (\Exception $e) {
                    try { $expiryCarbon = Carbon::parse($expiryParam, 'Asia/Kolkata'); } catch (\Exception $e2) {}
                }
            }

            $isLive         = false;
            $lastTradingDay = $this->getLastTradingDay($now);

            if ($expiryCarbon) {
                $expOnly  = $expiryCarbon->copy()->startOfDay();
                $todayOnly = $now->copy()->startOfDay();
                if ($expOnly->lt($todayOnly)) {
                    $toDate = $this->getLastTradingDay($expiryCarbon)->copy()->setTime(15,30,0)->format('Y-m-d H:i:s');
                    $isLive = false;
                } elseif ($expOnly->eq($todayOnly)) {
                    $isLive = ($nowHHMM >= 915 && $nowHHMM <= 1530);
                    $toDate = $isLive ? $now->copy()->subMinute()->format('Y-m-d H:i:s') : $now->copy()->setTime(15,30,0)->format('Y-m-d H:i:s');
                } else {
                    $isLive = $lastTradingDay->isSameDay($now) && $nowHHMM >= 915 && $nowHHMM <= 1530;
                    $toDate = $isLive ? $now->copy()->subMinute()->format('Y-m-d H:i:s') : $lastTradingDay->copy()->setTime(15,30,0)->format('Y-m-d H:i:s');
                }
            } else {
                $isLive = $lastTradingDay->isSameDay($now) && $nowHHMM >= 915 && $nowHHMM <= 1530;
                $toDate = $isLive ? $now->copy()->subMinute()->format('Y-m-d H:i:s') : $lastTradingDay->copy()->setTime(15,30,0)->format('Y-m-d H:i:s');
            }

            $daysBack = match($interval) {
                'ONE_MINUTE','THREE_MINUTE','FIVE_MINUTE' => 5,
                'FIFTEEN_MINUTE' => 10,
                'THIRTY_MINUTE'  => 20,
                'ONE_HOUR'       => 30,
                'ONE_DAY'        => 365,
                default          => 5,
            };

            $toCarbon = Carbon::parse($toDate, 'Asia/Kolkata');
            $fromDay  = $toCarbon->copy()->subDays($daysBack);
            while ($fromDay->isWeekend()) $fromDay->subDay();
            $fromDate = $fromDay->format('Y-m-d') . ' 09:15:00';

            $cacheKey = "candle_v9_{$symbolToken}_{$interval}_" . ($isLive ? $now->format('YmdHi') : $toCarbon->format('Ymd'));
            $cacheTtl = $isLive ? 30 : 7200;

            $data = Cache::remember($cacheKey, $cacheTtl, function () use ($token, $symbolToken, $exchange, $interval, $fromDate, $toDate) {
                $r    = Http::timeout(20)->withHeaders($this->getHeaders($token))
                    ->post("https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getCandleData", [
                        "exchange" => $exchange, "symboltoken" => $symbolToken,
                        "interval" => $interval, "fromdate"    => $fromDate, "todate" => $toDate,
                    ]);
                $json = $r->json();
                if (empty($json['data'])) Log::warning('getCandleData empty', compact('symbolToken','interval','fromDate','toDate'));
                $raw = $json['data'] ?? [];
                return is_array($raw) ? $raw : [];
            });

            if (empty($data)) {
                return response()->json(['success' => false, 'message' => "No data — {$interval} | {$fromDate} → {$toDate}", 'data' => []]);
            }
            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('getCandleData: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  NIFTY SYMBOL PARSER — 3-pass, validates strike range 10000–40000
    //  Fixes: "2622500" bug where year digits merged with strike
    // ══════════════════════════════════════════════════════════════════════════
    private function parseNiftySymbol(string $symbol): ?array
    {
        if (empty($symbol)) return null;

        // Pass 1: NIFTY17MAR2622500CE — standard format
        if (preg_match('/^NIFTY(\d{2})([A-Z]{3})(\d{2})(\d{3,6})(CE|PE)$/', $symbol, $m)) {
            $strike = (int)$m[4];
            if ($strike >= 10000 && $strike <= 40000 && $strike % 50 === 0) {
                return ['day'=>$m[1],'month'=>$m[2],'year'=>$m[3],'strike'=>$strike,'type'=>strtolower($m[5])];
            }
        }

        // Pass 2: NIFTY2622500CE — short weekly format (no day/month)
        if (preg_match('/^NIFTY(\d{2})(\d{5})(CE|PE)$/', $symbol, $m)) {
            $strike = (int)$m[2];
            if ($strike >= 10000 && $strike <= 40000 && $strike % 50 === 0) {
                return ['day'=>'','month'=>'','year'=>$m[1],'strike'=>$strike,'type'=>strtolower($m[3])];
            }
        }

        // Pass 3: Brute-force — strip year prefix if needed
        if (preg_match('/(\d+)(CE|PE)$/', $symbol, $m)) {
            $raw = (int)$m[1];
            $strike = $raw;
            if ($strike > 40000 && strlen((string)$raw) > 5) {
                $candidate = (int)substr((string)$raw, 2);
                if ($candidate >= 10000 && $candidate <= 40000 && $candidate % 50 === 0) {
                    $strike = $candidate;
                }
            }
            if ($strike >= 10000 && $strike <= 40000 && $strike % 50 === 0) {
                return ['day'=>'','month'=>'','year'=>'','strike'=>$strike,'type'=>strtolower($m[2])];
            }
        }

        return null;
    }

    // ─── Nifty Spot Price ─────────────────────────────────────────────────────
    private function getNiftySpotPrice(string $token): ?float
    {
        try {
            $r    = Http::timeout(10)->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", [
                    'mode' => 'LTP', 'exchangeTokens' => ['NSE' => ['99926000']],
                ]);
            $json = $r->json();
            if (empty($json['status']) || $json['status'] === false) return null;
            $ltp = $json['data']['fetched'][0]['ltp'] ?? null;
            return ($ltp !== null && (float)$ltp > 10000) ? (float)$ltp : null;
        } catch (\Exception $e) {
            Log::error('getNiftySpot: ' . $e->getMessage());
            return null;
        }
    }

    // ─── Symbol → Token Map ───────────────────────────────────────────────────
    private function getSymbolTokenMap(): array
    {
        return Cache::remember('angel_nifty_token_map_v2', 3600, function () {
            $scrips = json_decode(file_get_contents("https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json"), true);
            $map = [];
            if (!is_array($scrips)) return $map;
            foreach ($scrips as $s) {
                if (($s['exch_seg'] ?? '') === 'NFO' && ($s['name'] ?? '') === 'NIFTY') {
                    $map[$s['symbol']] = $s['token'];
                }
            }
            return $map;
        });
    }

    // ─── Last Trading Day ─────────────────────────────────────────────────────
    private function getLastTradingDay(Carbon $now): Carbon
    {
        $day = $now->copy()->startOfDay();
        while ($day->isWeekend()) $day->subDay();
        if ($day->isSameDay($now) && $now->format('H:i') < '09:15') {
            $day->subDay();
            while ($day->isWeekend()) $day->subDay();
        }
        return $day;
    }

    // ─── Available Expiries ───────────────────────────────────────────────────
    private function getAvailableExpiries(): array
    {
        return Cache::remember('nifty_expiry_list_v4', 3600, function () {
            $scrips   = json_decode(file_get_contents("https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json"), true);
            $expiries = [];
            $today    = strtotime(date('Y-m-d'));
            if (!is_array($scrips)) return $expiries;
            foreach ($scrips as $s) {
                if (($s['name']??'') === 'NIFTY' && ($s['exch_seg']??'') === 'NFO' && !empty($s['expiry'])) {
                    if (strtotime($s['expiry']) >= $today) $expiries[] = $s['expiry'];
                }
            }
            $unique = array_values(array_unique($expiries));
            usort($unique, fn($a,$b) => strtotime($a)-strtotime($b));
            return array_slice($unique, 0, 10);
        });
    }

    // ─── Merge Data ───────────────────────────────────────────────────────────
    private function mergeData(array $marketData, array $greeksData, array $strikePrices): array
    {
        $blank = ['ltp'=>0,'oi'=>0,'percentChange'=>0,'rho'=>0,'vega'=>0,'gamma'=>0,'theta'=>0,'delta'=>0,'iv'=>0,'tv_symbol'=>null,'symbol_token'=>null];
        $chain = [];
        foreach ($strikePrices as $s) $chain[$s] = ['strike'=>$s,'ce'=>$blank,'pe'=>$blank];

        $months = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06','JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];

        foreach ($marketData as $item) {
            if (!is_array($item)) continue;
            $parsed = $this->parseNiftySymbol($item['tradingSymbol'] ?? '');
            if (!$parsed) continue;
            $sk = $parsed['strike'];
            if (!isset($chain[$sk])) continue;
            $t  = $parsed['type'];
            $mn = $months[$parsed['month']] ?? '00';
            $chain[$sk][$t]['ltp']           = $item['ltp']          ?? 0;
            $chain[$sk][$t]['oi']            = $item['opnInterest']   ?? 0;
            $chain[$sk][$t]['percentChange'] = $item['percentChange'] ?? 0;
            $chain[$sk][$t]['tv_symbol']     = !empty($parsed['day'])
                ? "NIFTY{$parsed['year']}{$mn}{$parsed['day']}".($t==='ce'?'C':'P')."{$sk}"
                : null;
            $chain[$sk][$t]['symbol_token']  = $item['symbolToken']   ?? null;
        }

        foreach ($greeksData as $greek) {
            if (!is_array($greek)) continue;
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

    // ─── Strike Prices ────────────────────────────────────────────────────────
    private function generateStrikePrices($spot): array
    {
        $atm = (int)(round($spot / 50) * 50);
        return range($atm - 1000, $atm + 1000, 50);
    }

    // ─── Build Symbols ────────────────────────────────────────────────────────
    private function buildOptionSymbols(array $strikes, string $expiry): array
    {
        $symbols = [];
        $fExp    = strtoupper($expiry);
        if (strlen($fExp) > 7) $fExp = substr($fExp, 0, 5) . substr($fExp, -2);
        foreach ($strikes as $s) {
            $symbols[] = "NIFTY{$fExp}{$s}CE";
            $symbols[] = "NIFTY{$fExp}{$s}PE";
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

    // ─── Option Greeks ────────────────────────────────────────────────────────
    private function getOptionGreeks($token, string $name, string $expiry): array
    {
        try {
            $r    = Http::timeout(10)->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . "/rest/secure/angelbroking/marketData/v1/optionGreek", [
                    "name" => $name, "expirydate" => $expiry,
                ]);
            $data = $r->json()['data'] ?? [];
            if (!is_array($data)) { Log::warning('getOptionGreeks non-array', ['data'=>$data]); return []; }
            return $data;
        } catch (\Exception $e) {
            Log::error('getOptionGreeks: ' . $e->getMessage());
            return [];
        }
    }

    // ─── Headers ──────────────────────────────────────────────────────────────
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
