<?php

namespace App\Services\Sensex;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Services\Angel\AngelHeaderService;

class SensexOptionDataService
{
    public function __construct(

        private readonly AngelHeaderService $headers,

        private readonly SensexSpotService $spotService,

        private readonly SensexExpiryService $expiryService,

        private readonly SensexScripService $scripService,

        private readonly SensexStrikeService $strikeService,

        private readonly SensexQuoteService $quoteService,

        private readonly SensexChainBuilderService $builderService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN
    |--------------------------------------------------------------------------
    */

    public function get(Request $request): array
    {
        /*
        |--------------------------------------------------------------------------
        | TOKEN
        |--------------------------------------------------------------------------
        */

        $token =
            session('angel_jwt');

        if (!$token) {

            return [

                'success' => false,

                'message' =>
                    'Angel login required'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | HEADERS
        |--------------------------------------------------------------------------
        */

        $headers =
            $this->headers
                ->headers($token);

        /*
        |--------------------------------------------------------------------------
        | SPOT
        |--------------------------------------------------------------------------
        */

        $sensexSpot =
            $this->spotService
                ->get($headers);

        if (!$sensexSpot) {

            return [

                'success' => false,

                'message' =>
                    'Unable to fetch SENSEX spot'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ATM
        |--------------------------------------------------------------------------
        */

        $atm =
            $this->spotService
                ->atm($sensexSpot);

        /*
        |--------------------------------------------------------------------------
        | STRIKE RANGE
        |--------------------------------------------------------------------------
        */

        [
            $minStrike,
            $maxStrike
        ] = $this->strikeService
            ->range($atm);

        /*
        |--------------------------------------------------------------------------
        | SCRIP MASTER
        |--------------------------------------------------------------------------
        */

        $scripMaster =
            $this->scripService
                ->master();

        /*
        |--------------------------------------------------------------------------
        | EXPIRIES
        |--------------------------------------------------------------------------
        */

        $allExpiries =
            $this->expiryService
                ->get($scripMaster);

        /*
        |--------------------------------------------------------------------------
        | SELECTED EXPIRY
        |--------------------------------------------------------------------------
        */

        $selectedExpiry =
            $request->get(

                'expiry',

                $allExpiries[0] ?? null
            );

        /*
        |--------------------------------------------------------------------------
        | FILTER OPTIONS
        |--------------------------------------------------------------------------
        */

        $filteredOptions = [];

        foreach ($scripMaster as $item) {

            /*
            |--------------------------------------------------------------------------
            | BASIC FILTER
            |--------------------------------------------------------------------------
            */

            if (

                strtoupper(
                    $item['name'] ?? ''
                ) !== 'SENSEX'
            ) {
                continue;
            }

            if (

                strtoupper(
                    $item['exch_seg'] ?? ''
                ) !== 'BFO'
            ) {
                continue;
            }

            if (

                ($item['expiry'] ?? '')
                !== $selectedExpiry
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | SYMBOL
            |--------------------------------------------------------------------------
            */

            $symbol =
                strtoupper(
                    trim(
                        $item['symbol'] ?? ''
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | OPTION TYPE
            |--------------------------------------------------------------------------
            */

            $type = null;

            if (

                str_ends_with(
                    $symbol,
                    'CE'
                )
            ) {

                $type = 'ce';

            } elseif (

                str_ends_with(
                    $symbol,
                    'PE'
                )
            ) {

                $type = 'pe';

            } else {

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | STRIKE
            |--------------------------------------------------------------------------
            */

            $strike =
                $this->strikeService
                    ->extract($symbol);

            if (!$strike) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | RANGE FILTER
            |--------------------------------------------------------------------------
            */

            if (

                $strike < $minStrike

                ||

                $strike > $maxStrike
            ) {
                continue;
            }

            $filteredOptions[] = [

                'symbol' =>
                    $symbol,

                'token' =>
                    (string)$item['token'],

                'strike' =>
                    $strike,

                'type' =>
                    $type
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | TOKENS
        |--------------------------------------------------------------------------
        */

        $allTokens = [];

        foreach (

            $filteredOptions
            as $option
        ) {

            $allTokens[] =
                $option['token'];
        }

        /*
        |--------------------------------------------------------------------------
        | QUOTES
        |--------------------------------------------------------------------------
        */

        $quotes =
            $this->quoteService
                ->quotes(
                    $headers,
                    $allTokens
                );

        /*
        |--------------------------------------------------------------------------
        | BUILD CHAIN
        |--------------------------------------------------------------------------
        */

        $optionsData =
            $this->builderService
                ->build(
                    $filteredOptions,
                    $quotes
                );

        /*
        |--------------------------------------------------------------------------
        | CACHE
        |--------------------------------------------------------------------------
        */

        Cache::put(

            'sensex_spot',

            $sensexSpot,

            300
        );

        Cache::put(

            'sensex_option_chain_' .
            $selectedExpiry,

            $optionsData,

            300
        );

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return [

            'success' => true,

            'data' =>
                $optionsData,

            'sensexSpot' =>
                $sensexSpot,

            'atm' =>
                $atm,

            'expiries' =>
                $allExpiries,

            'selectedExpiry' =>
                $selectedExpiry,

            'time' =>
                now()->format('H:i:s')
        ];
    }
}

