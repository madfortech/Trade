<?php

namespace App\Http\Controllers\Sensex;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SensexOptionChainController extends Controller
{
    protected string $baseUrl =
        'https://apiconnect.angelone.in';

    /*
    |--------------------------------------------------------------------------
    | HEADERS
    |--------------------------------------------------------------------------
    */

    protected function headers(string $token): array
    {
        return [

            'Content-Type' =>
                'application/json',

            'Accept' =>
                'application/json',

            'Authorization' =>
                'Bearer ' . $token,

            'X-UserType' =>
                'USER',

            'X-SourceID' =>
                'WEB',

            'X-ClientLocalIP' =>
                '127.0.0.1',

            'X-ClientPublicIP' =>
                '127.0.0.1',

            'X-MACAddress' =>
                '00:00:00:00:00:00',

            'X-PrivateKey' =>
                env('ANGEL_API_KEY')
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | AUTH TOKEN
            |--------------------------------------------------------------------------
            */

            $token =
                session('angel_jwt');

            if (!$token) {

                return response()->json([

                    'success' => false,

                    'message' =>
                        'Angel login required'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | FETCH SENSEX SPOT
            |--------------------------------------------------------------------------
            */

            $spotResponse = Http::withHeaders(

                $this->headers($token)

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

            $spotJson =
                $spotResponse->json();

            $sensexSpot =
                data_get(

                    $spotJson,

                    'data.fetched.0.ltp',

                    0
                );

            if (!$sensexSpot) {

                return response()->json([

                    'success' => false,

                    'message' =>
                        'Unable to fetch SENSEX spot',

                    'raw' =>
                        $spotJson
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ATM STRIKE
            |--------------------------------------------------------------------------
            */

            $atm =
                round($sensexSpot / 100) * 100;

            /*
            |--------------------------------------------------------------------------
            | LOAD SCRIP MASTER
            |--------------------------------------------------------------------------
            */

            $scripMaster =
                Cache::remember(

                    'angel_scrip_master',

                    3600,

                    function () {

                        return json_decode(

                            file_get_contents(

                                'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json'
                            ),

                            true
                        );
                    }
                );

            /*
            |--------------------------------------------------------------------------
            | AVAILABLE EXPIRIES
            |--------------------------------------------------------------------------
            */

            $allExpiries = [];

            $today =
                now()->startOfDay();

            foreach ($scripMaster as $item) {

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

                $expiry =
                    trim(
                        $item['expiry'] ?? ''
                    );

                if (!$expiry) {
                    continue;
                }

                try {

                    $expiryDate = null;

                    /*
                    |--------------------------------------------------------------------------
                    | FORMAT: 07MAY2026
                    |--------------------------------------------------------------------------
                    */

                    if (

                        preg_match(
                            '/^\d{2}[A-Z]{3}\d{4}$/',
                            $expiry
                        )
                    ) {

                        $expiryDate =
                            Carbon::createFromFormat(
                                'dMY',
                                strtoupper($expiry)
                            );
                    }

                    if (
                        !$expiryDate
                    ) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | REMOVE EXPIRED
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $expiryDate->lt($today)
                    ) {
                        continue;
                    }

                    $allExpiries[] =
                        $expiry;

                } catch (\Exception $e) {

                    continue;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | UNIQUE
            |--------------------------------------------------------------------------
            */

            $allExpiries =
                array_values(
                    array_unique(
                        $allExpiries
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | SORT ASC
            |--------------------------------------------------------------------------
            */

            usort(

                $allExpiries,

                function ($a, $b) {

                    $da =
                        Carbon::createFromFormat(
                            'dMY',
                            strtoupper($a)
                        );

                    $db =
                        Carbon::createFromFormat(
                            'dMY',
                            strtoupper($b)
                        );

                    return
                        $da->timestamp
                        <=>
                        $db->timestamp;
                }
            );

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
            | STRIKE RANGE
            |--------------------------------------------------------------------------
            */

            $minStrike =
                $atm - 1000;

            $maxStrike =
                $atm + 1000;

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
                | STRIKE EXTRACTION
                |--------------------------------------------------------------------------
                |
                | Example:
                | SENSEX2650766700PE
                | Strike = 66700
                |
                */

                preg_match(

                    '/(\d{5})(CE|PE)$/',

                    $symbol,

                    $matches
                );

                if (
                    !isset($matches[1])
                ) {
                    continue;
                }

                $strike =
                    (int)$matches[1];

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
            | FETCH QUOTES
            |--------------------------------------------------------------------------
            */

            $quoteResponse = Http::withHeaders(

                $this->headers($token)

            )->post(

                $this->baseUrl .
                '/rest/secure/angelbroking/market/v1/quote/',

                [

                    "mode" => "FULL",

                    "exchangeTokens" => [

                        "BFO" =>
                            $allTokens
                    ]
                ]
            );

            $quoteJson =
                $quoteResponse->json();

            $fetched =
                data_get(

                    $quoteJson,

                    'data.fetched',

                    []
                );

            /*
            |--------------------------------------------------------------------------
            | MAP QUOTES
            |--------------------------------------------------------------------------
            */

            $quoteMap = [];

            foreach (

                $fetched
                as $item
            ) {

                $quoteMap[
                    (string)$item['symbolToken']
                ] = $item;
            }

            /*
            |--------------------------------------------------------------------------
            | BUILD OPTION CHAIN
            |--------------------------------------------------------------------------
            */

            $optionsData = [];

            foreach (

                $filteredOptions
                as $option
            ) {

                $token =
                    $option['token'];

                if (

                    !isset(
                        $quoteMap[$token]
                    )
                ) {
                    continue;
                }

                $quote =
                    $quoteMap[$token];

                $strike =
                    $option['strike'];

                $type =
                    $option['type'];

                $optionsData[$strike][$type] = [

                    'ltp' =>
                        $quote['ltp'] ?? 0,

                    'oi' =>
                        $quote['opnInterest'] ?? 0,

                    'percentChange' =>
                        $quote['percentChange'] ?? 0,

                    'symbol' =>
                        $option['symbol'],

                    'symbol_token' =>
                        $token
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | SORT STRIKES
            |--------------------------------------------------------------------------
            */

            ksort($optionsData);

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
            | AJAX
            |--------------------------------------------------------------------------
            */

            if ($request->ajax()) {

                return response()->json([

                    'success' => true,

                    'data' => $optionsData,

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
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | VIEW
            |--------------------------------------------------------------------------
            */

            return view(

                'sensex.option-data',

                [

                    'optionsData' =>
                        $optionsData,

                    'sensexSpot' =>
                        $sensexSpot,

                    'atmStrike' =>
                        $atm,

                    'allExpiries' =>
                        $allExpiries,

                    'selectedExpiry' =>
                        $selectedExpiry
                ]
            );

        } catch (\Exception $e) {

            Log::error(

                'Sensex Option Chain Error: ' .

                $e->getMessage()
            );

            return response()->json([

                'success' => false,

                'message' =>
                    $e->getMessage()
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REFRESH
    |--------------------------------------------------------------------------
    */

    public function refresh(Request $request)
    {
        return $this->index($request);
    }
}

