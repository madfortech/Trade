<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SensexOptionDataService
{
    private string $baseUrl = 'https://apiconnect.angelone.in';

    public function isMarketOpen(): bool
    {
        $now = Carbon::now('Asia/Kolkata');
        $hhmm = (int) $now->format('G') * 100 + (int) $now->format('i');

        return $now->isWeekday() && $hhmm >= 915 && $hhmm <= 1530;
    }

    public function lastTradingDay(Carbon $now): Carbon
    {
        $day = $now->copy()->setTimezone('Asia/Kolkata');

        if ($day->isWeekday()) {
            return $day;
        }

        while ($day->isWeekend()) {
            $day->subDay();
        }

        return $day;
    }

    public function getSensexSpotPrice(string $token): ?float
    {
        try {
            $res = Http::timeout(10)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode' => 'LTP',
                    'exchangeTokens' => ['BSE' => ['99919000']],
                ]);

            return $res->json()['data']['fetched'][0]['ltp'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function filterAtmStrikes(array $map, float $spot, int $range): array
    {
        if (empty($map)) {
            return [];
        }

        $strikes = array_keys($map);
        sort($strikes);

        $closestIdx = 0;
        $minDiff = PHP_INT_MAX;

        foreach ($strikes as $i => $s) {
            $diff = abs($s - $spot);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestIdx = $i;
            }
        }

        $selected = array_slice($strikes, max(0, $closestIdx - $range), ($range * 2) + 1);

        $final = [];
        foreach ($selected as $s) {
            $final[$s] = $map[$s];
        }

        return $final;
    }

    public function fetchMarketDataInBulk(string $token, array $map): array
    {
        if (empty($map)) {
            return [];
        }

        $tokens = [];
        foreach ($map as $row) {
            if (!empty($row['ce'])) {
                $tokens[] = $row['ce'];
            }
            if (!empty($row['pe'])) {
                $tokens[] = $row['pe'];
            }
        }

        try {
            $res = Http::timeout(30)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode' => 'FULL',
                    'exchangeTokens' => ['BFO' => $tokens],
                ]);
            $raw = $res->json();
            $data = collect($raw['data']['fetched'] ?? [])
                ->keyBy(fn ($i) => $i['symbolToken'] ?? $i['symboltoken'] ?? '');
        } catch (\Exception $e) {
            $data = collect([]);
        }

        $out = [];
        foreach ($map as $strike => $t) {
            $ce = $data[$t['ce'] ?? ''] ?? [];
            $pe = $data[$t['pe'] ?? ''] ?? [];

            $out[$strike] = [
                'ce' => [
                    'ltp' => $ce['ltp'] ?? 0,
                    'oi' => $ce['opnInterest'] ?? 0,
                    'percentChange' => $ce['percentChange'] ?? 0,
                    'symbol_token' => $t['ce'] ?? null,
                ],
                'pe' => [
                    'ltp' => $pe['ltp'] ?? 0,
                    'oi' => $pe['opnInterest'] ?? 0,
                    'percentChange' => $pe['percentChange'] ?? 0,
                    'symbol_token' => $t['pe'] ?? null,
                ],
            ];
        }

        return $out;
    }

    public function fetchLtpQuote(string $token, string $exchange, string $symbolToken): ?array
    {
        try {
            $res = Http::timeout(10)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    'mode' => 'LTP',
                    'exchangeTokens' => [$exchange => [$symbolToken]],
                ]);

            return $res->json();
        } catch (\Exception $e) {
            return ['exception' => $e->getMessage()];
        }
    }

    public function fetchCandleData(string $token, string $exchange, string $symbolToken, string $interval, string $fromDateStr, string $toDateStr): ?array
    {
        try {
            $res = Http::timeout(25)
                ->withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/rest/secure/angelbroking/historical/v1/getCandleData', [
                    'exchange' => $exchange,
                    'symboltoken' => $symbolToken,
                    'interval' => $interval,
                    'fromdate' => $fromDateStr,
                    'todate' => $toDateStr,
                ]);

            return $res->json();
        } catch (\Exception $e) {
            return ['exception' => $e->getMessage()];
        }
    }

    public function getHeaders(string $token): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-PrivateKey' => env('ANGEL_API_KEY'),
            'X-UserType' => 'USER',
            'X-SourceID' => 'WEB',
            'X-ClientLocalIP' => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',
            'X-MACAddress' => '00:00:00:00:00:00',
            'Accept' => 'application/json',
        ];
    }
}
