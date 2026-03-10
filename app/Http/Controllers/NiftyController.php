<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class NiftyController extends Controller
{
    public function index(Request $request)
{
    set_time_limit(120);
    ini_set('default_socket_timeout', 120);

    // ── Default values — agar kuch fail ho to bhi view load ho ──────────
    $allExpiries    = [];
    $selectedExpiry = '';
    $optionsData    = [];
    $niftySpot      = 0;
    $marketStatus   = $this->getMarketStatus();

    try {
        $token = session('angel_jwt');
        if (!$token) {
            return redirect()->route('angel.login')->with('error', 'Please login first.');
        }

        // 1. Get All Available Expiries from Scrip Master
        $allExpiries = $this->getAvailableExpiries();

        // 2. Selected Expiry (default = first)
        $selectedExpiry = $request->get('expiry', $allExpiries[0] ?? '');

        // 3. Nifty Spot Price
        $niftySpot = $this->getNiftySpotPrice($token);
        if (!$niftySpot) {
            $niftySpot = Cache::get('last_nifty_spot', 25000.00);
        } else {
            Cache::put('last_nifty_spot', $niftySpot, 86400);
        }

        // 4. Generate Strike Prices & Symbols
        $strikePrices  = $this->generateStrikePrices($niftySpot);
        $optionSymbols = $this->buildOptionSymbols($strikePrices, $selectedExpiry);
        $exchangeTokens = $this->processScripMaster($optionSymbols);

        // 5. Fetch Market Quotes
        $marketData = [];
        if (!empty($exchangeTokens['NFO'])) {
            $response = Http::withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", [
                    'mode'           => 'FULL',
                    'exchangeTokens' => $exchangeTokens
                ]);

            $apiRes = $response->json();

            if (isset($apiRes['data']['fetched']) && !empty($apiRes['data']['fetched'])) {
                $marketData = $apiRes['data']['fetched'];
                Cache::put("nifty_chain_{$selectedExpiry}_cache", $marketData, 86400);
            } else {
                $marketData = Cache::get("nifty_chain_{$selectedExpiry}_cache", []);
            }
        }

        // 6. Fetch Greeks
        $greekExpiry = Carbon::parse($selectedExpiry)->format('d-M-Y');
        $greeksData  = $this->getOptionGreeks($token, "NIFTY", $greekExpiry);

        // 7. Merge All Data
        $optionsData = $this->mergeData($marketData, $greeksData, $strikePrices);

    } catch (\Exception $e) {
        Log::error('Option Chain Error: ' . $e->getMessage());
        // Error ke baad bhi view load hoga — blank data ke saath
    }

    return view('nifty.option-data', [
        'optionsData'    => $optionsData,
        'niftySpot'      => $niftySpot,
        'selectedExpiry' => $selectedExpiry,
        'allExpiries'    => $allExpiries,   // ← hamesha pass hoga, chahe empty array ho
        'marketStatus'   => $marketStatus,
    ]);
}


    private string $baseUrl = 'https://apiconnect.angelone.in';

    // ── Angel One API limits per interval ────────────────────────────────────
    // Angel One ek request mein itne hi din ka data deta hai
    private array $chunkDays = [
        'ONE_MINUTE'      => 30,
        'THREE_MINUTE'    => 60,
        'FIVE_MINUTE'     => 60,
        'FIFTEEN_MINUTE'  => 60,
        'THIRTY_MINUTE'   => 60,
        'ONE_HOUR'        => 400,
        'ONE_DAY'         => 2000,
    ];

    public function chart()
    {
        return view('trading.nifty', [
            'clientCode' => session('clientCode'),
            'feedToken'  => session('feedToken'),
            'apiKey'     => env('ANGEL_API_KEY'),
            'profile'    => session('profile'),
        ]);
    }

    public function historicalData(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['candles' => [], 'error' => 'Not logged in']);
        }

        $interval = $request->query('interval', '5m');

        // ── Interval map ──────────────────────────────────────────────────────
        // totalDays = kitne din peeche tak data chahiye
        $intervalMap = [
            '3m'  => ['angel' => 'THREE_MINUTE',   'totalDays' => 60],
            '5m'  => ['angel' => 'FIVE_MINUTE',     'totalDays' => 60],
            '15m' => ['angel' => 'FIFTEEN_MINUTE',  'totalDays' => 180],
            '1h'  => ['angel' => 'ONE_HOUR',         'totalDays' => 365],
            '1d'  => ['angel' => 'ONE_DAY',          'totalDays' => 365],
        ];

        if (!isset($intervalMap[$interval])) {
            $interval = '5m';
        }

        $angelInterval = $intervalMap[$interval]['angel'];
        $totalDays     = $intervalMap[$interval]['totalDays'];
        $chunkSize     = $this->chunkDays[$angelInterval] ?? 60;

        // ── Date range calculate karo ─────────────────────────────────────────
        $now    = Carbon::now('Asia/Kolkata');
        $endDt  = $now->copy();
        $startDt = $now->copy()->subDays($totalDays)->setTime(9, 15, 0);

        // ── Chunks banao ──────────────────────────────────────────────────────
        // Agar totalDays <= chunkSize → ek hi request
        // Agar totalDays >  chunkSize → multiple requests in loop
        $chunks = [];
        $chunkEnd = $endDt->copy();

        while ($chunkEnd->gt($startDt)) {
            $chunkStart = $chunkEnd->copy()->subDays($chunkSize);
            if ($chunkStart->lt($startDt)) {
                $chunkStart = $startDt->copy();
            }
            $chunks[] = [
                'from' => $chunkStart->format('Y-m-d H:i'),
                'to'   => $chunkEnd->format('Y-m-d H:i'),
            ];
            $chunkEnd = $chunkStart->copy()->subMinutes(1);
        }

        // ── Sabse pehle ka chunk pehle aana chahiye ───────────────────────────
        $chunks = array_reverse($chunks);

        // ── Fetch all chunks ──────────────────────────────────────────────────
        $allCandles = [];
        $headers = [
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

        foreach ($chunks as $chunk) {
            try {
                $response = Http::timeout(20)
                    ->withHeaders($headers)
                    ->post($this->baseUrl . '/rest/secure/angelbroking/historical/v1/getCandleData', [
                        'exchange'    => 'NSE',
                        'symboltoken' => '99926000',
                        'interval'    => $angelInterval,
                        'fromdate'    => $chunk['from'],
                        'todate'      => $chunk['to'],
                    ]);

                $result = $response->json();

                if (!empty($result['data']) && is_array($result['data'])) {
                    $allCandles = array_merge($allCandles, $result['data']);
                }

                // Rate limit avoid karo — Angel One 3 req/sec allow karta hai
                usleep(400000); // 400ms wait

            } catch (\Exception $e) {
                // Ek chunk fail ho to skip karo, baaki continue karo
                continue;
            }
        }

        // ── Duplicates remove karo + sort karo ───────────────────────────────
        $seen = [];
        $unique = [];
        foreach ($allCandles as $candle) {
            $key = $candle[0]; // timestamp
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $candle;
            }
        }

        // Time ke order mein sort karo
        usort($unique, fn($a, $b) => strcmp($a[0], $b[0]));

        return response()->json([
            'candles' => $unique,
            'total'   => count($unique),
            'chunks'  => count($chunks),
        ]);
    }
}
