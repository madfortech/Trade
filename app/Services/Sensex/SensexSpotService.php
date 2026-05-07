<?php

namespace App\Services\Sensex;

use Illuminate\Support\Facades\Http;

class SensexSpotService
{
    protected string $baseUrl =
        'https://apiconnect.angelone.in';

    public function get(
        array $headers
    ): float {

        $response = Http::withHeaders(

            $headers

        )->post(

            $this->baseUrl .
            '/rest/secure/angelbroking/market/v1/quote/',

            [

                "mode" => "FULL",

                "exchangeTokens" => [

                    "BSE" => [

                        "99919000"
                    ]
                ]
            ]
        );

        return (float)data_get(

            $response->json(),

            'data.fetched.0.ltp',

            0
        );
    }

    public function atm(
        float $spot
    ): int {

        return
            round($spot / 100) * 100;
    }
}

