<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AngelOneApiService
{
    private string $baseUrl = 'https://apiconnect.angelone.in';

    public function getHeaders(string $token): array
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

    public function getLtp(
        string $token,
        string $exchange,
        string $symbolToken
    ): array {

        return Http::timeout(15)
            ->withHeaders($this->getHeaders($token))
            ->post(
                $this->baseUrl .
                '/rest/secure/angelbroking/market/v1/quote/',
                [
                    'mode' => 'LTP',
                    'exchangeTokens' => [
                        $exchange => [$symbolToken]
                    ],
                ]
            )
            ->json();
    }

    public function getQuote(
        string $token,
        string $exchange,
        array $tokens
    ): array {

        return Http::timeout(30)
            ->withHeaders($this->getHeaders($token))
            ->post(
                $this->baseUrl .
                '/rest/secure/angelbroking/market/v1/quote/',
                [
                    'mode' => 'FULL',
                    'exchangeTokens' => [
                        $exchange => $tokens
                    ],
                ]
            )
            ->json();
    }

    public function getHistoricalData(
        string $token,
        string $exchange,
        string $symbolToken,
        string $interval,
        string $fromDate,
        string $toDate
    ): array {

        return Http::timeout(60)
            ->withHeaders($this->getHeaders($token))
            ->post(
                $this->baseUrl .
                '/rest/secure/angelbroking/historical/v1/getCandleData',
                [
                    'exchange'    => $exchange,
                    'symboltoken' => $symbolToken,
                    'interval'    => $interval,
                    'fromdate'    => $fromDate,
                    'todate'      => $toDate,
                ]
            )
            ->json();
    }
}