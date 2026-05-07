<?php

namespace App\Services\Sensex;

use Illuminate\Support\Facades\Http;

class SensexQuoteService
{
    protected string $baseUrl =
        'https://apiconnect.angelone.in';

    public function quotes(
        array $headers,
        array $tokens
    ): array {

        $response = Http::withHeaders(

            $headers

        )->post(

            $this->baseUrl .
            '/rest/secure/angelbroking/market/v1/quote/',

            [

                "mode" => "FULL",

                "exchangeTokens" => [

                    "BFO" =>
                        $tokens
                ]
            ]
        );

        return data_get(

            $response->json(),

            'data.fetched',

            []
        );
    }
}

