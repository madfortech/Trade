<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

trait InteractsWithNiftyOptionChain
{
    private function getAvailableExpiries(): array
    {
        return Cache::remember('nifty_expiry_list_v4', 3600, function () {
            $url = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';
            $scrips = json_decode(file_get_contents($url), true);

            $expiries = [];
            $today = strtotime(date('Y-m-d'));

            foreach ($scrips as $scrip) {
                if (
                    ($scrip['name'] ?? '') === 'NIFTY' &&
                    ($scrip['exch_seg'] ?? '') === 'NFO' &&
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

    private function generateStrikePrices(float $spot): array
    {
        $atm = (int) (round($spot / 50) * 50);

        return range($atm - 1000, $atm + 1000, 50);
    }

    private function buildOptionSymbols(array $strikes, string $expiry): array
    {
        $symbols = [];
        $formattedExpiry = strtoupper($expiry);

        if (strlen($formattedExpiry) > 7) {
            $formattedExpiry = substr($formattedExpiry, 0, 5) . substr($formattedExpiry, -2);
        }

        foreach ($strikes as $strike) {
            $symbols[] = "NIFTY{$formattedExpiry}{$strike}CE";
            $symbols[] = "NIFTY{$formattedExpiry}{$strike}PE";
        }

        return $symbols;
    }

    private function processScripMaster(array $optionSymbols): array
    {
        $tokenMap = Cache::remember('angel_nifty_token_map_v2', 3600, function () {
            $url = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';
            $scrips = json_decode(file_get_contents($url), true);

            $map = [];
            foreach ($scrips as $scrip) {
                if (($scrip['exch_seg'] ?? '') === 'NFO' && ($scrip['name'] ?? '') === 'NIFTY') {
                    $map[$scrip['symbol']] = $scrip['token'];
                }
            }

            return $map;
        });

        $tokens = [];
        foreach ($optionSymbols as $symbol) {
            if (isset($tokenMap[$symbol])) {
                $tokens[] = $tokenMap[$symbol];
            }
        }

        return ['NFO' => $tokens];
    }

    private function getMarketStatus(): array
    {
        $now = Carbon::now('Asia/Kolkata');
        $isMarketOpen = $now->isWeekday() && $now->between('09:15', '15:30');

        return [
            'is_open' => $isMarketOpen,
            'status' => $isMarketOpen ? 'Market Open' : 'Market Closed',
            'current_time' => $now->format('d-M-Y H:i:s'),
            'message' => $isMarketOpen ? 'Live Data' : 'Showing Last Close',
        ];
    }

    private function getNiftySpotPrice(string $token): ?float
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode' => 'LTP',
                    'exchangeTokens' => ['NSE' => ['99926000']],
                ]);

            return $response->json()['data']['fetched'][0]['ltp'] ?? null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function getOptionGreeks(string $token, string $name, string $expiry): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/marketData/v1/optionGreek', [
                    'name' => $name,
                    'expirydate' => $expiry,
                ]);

            return $response->json()['data'] ?? [];
        } catch (\Exception $exception) {
            return [];
        }
    }

    private function mergeData(array $marketData, array $greeksData, array $strikePrices): array
    {
        $chain = [];
        foreach ($strikePrices as $strike) {
            $chain[$strike] = [
                'strike' => $strike,
                'ce' => ['ltp' => 0, 'oi' => 0, 'percentChange' => 0, 'rho' => 0, 'vega' => 0, 'gamma' => 0, 'theta' => 0, 'delta' => 0, 'iv' => 0, 'tv_symbol' => null, 'symbol_token' => null],
                'pe' => ['ltp' => 0, 'oi' => 0, 'percentChange' => 0, 'rho' => 0, 'vega' => 0, 'gamma' => 0, 'theta' => 0, 'delta' => 0, 'iv' => 0, 'tv_symbol' => null, 'symbol_token' => null],
            ];
        }

        $months = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
        ];

        foreach ($marketData as $item) {
            if (!preg_match('/NIFTY(\d{2})([A-Z]{3})(\d{2})(\d+)(CE|PE)$/', $item['tradingSymbol'] ?? '', $matches)) {
                continue;
            }

            $strikeKey = (int) $matches[4];
            if (!isset($chain[$strikeKey])) {
                continue;
            }

            $type = strtolower($matches[5]);
            $monthNum = $months[$matches[2]] ?? '01';
            $tvSymbol = "NIFTY{$matches[3]}{$monthNum}{$matches[1]}" . ($type === 'ce' ? 'C' : 'P') . $matches[4];

            $chain[$strikeKey][$type]['ltp'] = $item['ltp'] ?? 0;
            $chain[$strikeKey][$type]['oi'] = $item['opnInterest'] ?? 0;
            $chain[$strikeKey][$type]['percentChange'] = $item['percentChange'] ?? 0;
            $chain[$strikeKey][$type]['tv_symbol'] = $tvSymbol;
            $chain[$strikeKey][$type]['symbol_token'] = $item['symbolToken'] ?? null;
        }

        foreach ($greeksData as $greek) {
            $strike = (int) ($greek['strikePrice'] ?? 0);
            $type = strtolower($greek['optionType'] ?? '');

            if ($strike > 0 && isset($chain[$strike][$type])) {
                $chain[$strike][$type]['delta'] = $greek['delta'] ?? 0;
                $chain[$strike][$type]['theta'] = $greek['theta'] ?? 0;
                $chain[$strike][$type]['vega'] = $greek['vega'] ?? 0;
                $chain[$strike][$type]['gamma'] = $greek['gamma'] ?? 0;
                $chain[$strike][$type]['iv'] = $greek['impliedVolatility'] ?? 0;
                $chain[$strike][$type]['rho'] = $greek['rho'] ?? 0;
            }
        }

        ksort($chain);

        return $chain;
    }

    private function getHeaders(string $token): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-UserType' => 'USER',
            'X-SourceID' => 'WEB',
            'X-PrivateKey' => env('ANGEL_API_KEY'),
            'X-ClientLocalIP' => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',
            'X-MACAddress' => '00:00:00:00:00:00',
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
