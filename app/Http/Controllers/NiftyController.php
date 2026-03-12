<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithNiftyOptionChain;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NiftyController extends Controller
{
    use InteractsWithNiftyOptionChain;

    private string $baseUrl = 'https://apiconnect.angelone.in';

    private array $chunkDays = [
        'ONE_MINUTE' => 30,
        'THREE_MINUTE' => 60,
        'FIVE_MINUTE' => 60,
        'FIFTEEN_MINUTE' => 60,
        'THIRTY_MINUTE' => 60,
        'ONE_HOUR' => 400,
        'ONE_DAY' => 2000,
    ];

    public function index(Request $request)
    {
        set_time_limit(120);
        ini_set('default_socket_timeout', 120);

        $allExpiries = [];
        $selectedExpiry = '';
        $optionsData = [];
        $niftySpot = 0;
        $marketStatus = $this->getMarketStatus();

        try {
            $token = session('angel_jwt');
            if (!$token) {
                return redirect()->route('angel.login')->with('error', 'Please login first.');
            }

            $allExpiries = $this->getAvailableExpiries();
            $selectedExpiry = $request->get('expiry', $allExpiries[0] ?? '');

            $niftySpot = $this->getNiftySpotPrice($token);
            if (!$niftySpot) {
                $niftySpot = Cache::get('last_nifty_spot', 25000.00);
            } else {
                Cache::put('last_nifty_spot', $niftySpot, 86400);
            }

            $strikePrices = $this->generateStrikePrices($niftySpot);
            $optionSymbols = $this->buildOptionSymbols($strikePrices, $selectedExpiry);
            $exchangeTokens = $this->processScripMaster($optionSymbols);

            $marketData = $this->fetchOptionMarketData($token, $selectedExpiry, $exchangeTokens);
            $greekExpiry = Carbon::parse($selectedExpiry)->format('d-M-Y');
            $greeksData = $this->getOptionGreeks($token, 'NIFTY', $greekExpiry);
            $optionsData = $this->mergeData($marketData, $greeksData, $strikePrices);
        } catch (\Exception $exception) {
            Log::error('Option Chain Error: ' . $exception->getMessage());
        }

        return view('nifty.option-data', [
            'optionsData' => $optionsData,
            'niftySpot' => $niftySpot,
            'selectedExpiry' => $selectedExpiry,
            'allExpiries' => $allExpiries,
            'marketStatus' => $marketStatus,
        ]);
    }

    public function chart()
    {
        return view('trading.nifty', [
            'clientCode' => session('clientCode'),
            'feedToken' => session('feedToken'),
            'apiKey' => env('ANGEL_API_KEY'),
            'profile' => session('profile'),
        ]);
    }

    public function historicalData(Request $request)
    {
        $token = session('angel_jwt');

        if (!$token) {
            return response()->json(['candles' => [], 'error' => 'Not logged in']);
        }

        [$angelInterval, $totalDays] = $this->resolveIntervalConfig($request->query('interval', '5m'));
        $chunkSize = $this->chunkDays[$angelInterval] ?? 60;

        $now = Carbon::now('Asia/Kolkata');
        $endDt = $now->copy();
        $startDt = $now->copy()->subDays($totalDays)->setTime(9, 15, 0);
        $chunks = $this->buildDateChunks($startDt, $endDt, $chunkSize);

        $allCandles = $this->fetchHistoricalChunks($token, $chunks, $angelInterval);
        $unique = $this->dedupeAndSortCandles($allCandles);

        return response()->json([
            'candles' => $unique,
            'total' => count($unique),
            'chunks' => count($chunks),
        ]);
    }

    private function fetchOptionMarketData(string $token, string $selectedExpiry, array $exchangeTokens): array
    {
        if (empty($exchangeTokens['NFO'])) {
            return [];
        }

        $response = Http::withHeaders($this->getHeaders($token))
            ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                'mode' => 'FULL',
                'exchangeTokens' => $exchangeTokens,
            ]);

        $apiRes = $response->json();
        if (isset($apiRes['data']['fetched']) && !empty($apiRes['data']['fetched'])) {
            $marketData = $apiRes['data']['fetched'];
            Cache::put("nifty_chain_{$selectedExpiry}_cache", $marketData, 86400);

            return $marketData;
        }

        return Cache::get("nifty_chain_{$selectedExpiry}_cache", []);
    }

    private function resolveIntervalConfig(string $interval): array
    {
        $intervalMap = [
            '3m' => ['angel' => 'THREE_MINUTE', 'totalDays' => 60],
            '5m' => ['angel' => 'FIVE_MINUTE', 'totalDays' => 60],
            '15m' => ['angel' => 'FIFTEEN_MINUTE', 'totalDays' => 180],
            '1h' => ['angel' => 'ONE_HOUR', 'totalDays' => 365],
            '1d' => ['angel' => 'ONE_DAY', 'totalDays' => 365],
        ];

        $normalizedInterval = isset($intervalMap[$interval]) ? $interval : '5m';

        return [
            $intervalMap[$normalizedInterval]['angel'],
            $intervalMap[$normalizedInterval]['totalDays'],
        ];
    }

    private function buildDateChunks(Carbon $startDt, Carbon $endDt, int $chunkSize): array
    {
        $chunks = [];
        $chunkEnd = $endDt->copy();

        while ($chunkEnd->gt($startDt)) {
            $chunkStart = $chunkEnd->copy()->subDays($chunkSize);
            if ($chunkStart->lt($startDt)) {
                $chunkStart = $startDt->copy();
            }

            $chunks[] = [
                'from' => $chunkStart->format('Y-m-d H:i'),
                'to' => $chunkEnd->format('Y-m-d H:i'),
            ];
            $chunkEnd = $chunkStart->copy()->subMinutes(1);
        }

        return array_reverse($chunks);
    }

    private function fetchHistoricalChunks(string $token, array $chunks, string $angelInterval): array
    {
        $allCandles = [];
        $headers = $this->getHeaders($token);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::timeout(20)
                    ->withHeaders($headers)
                    ->post($this->baseUrl . '/rest/secure/angelbroking/historical/v1/getCandleData', [
                        'exchange' => 'NSE',
                        'symboltoken' => '99926000',
                        'interval' => $angelInterval,
                        'fromdate' => $chunk['from'],
                        'todate' => $chunk['to'],
                    ]);

                $result = $response->json();
                if (!empty($result['data']) && is_array($result['data'])) {
                    $allCandles = array_merge($allCandles, $result['data']);
                }

                usleep(400000);
            } catch (\Exception $exception) {
                continue;
            }
        }

        return $allCandles;
    }

    private function dedupeAndSortCandles(array $candles): array
    {
        $seen = [];
        $unique = [];

        foreach ($candles as $candle) {
            $key = $candle[0] ?? null;
            if ($key && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $candle;
            }
        }

        usort($unique, fn($a, $b) => strcmp($a[0], $b[0]));

        return $unique;
    }
}
