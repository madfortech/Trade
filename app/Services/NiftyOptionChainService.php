<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NiftyOptionChainService
{
    public function __construct(
        private AngelOneApiService $angelApi,
        private ExpiryService $expiryService
    ) {
    }

    /**
     * Initial page load
     */
    public function index(Request $request)
    {
        return view('nifty.option-data', [
            'optionsData'    => [],
            'niftySpot'      => 22500,
            'selectedExpiry' => '',
            'allExpiries'    => [],
            'marketStatus'   => false,
        ]);
    }
    /**
     * AJAX refresh
     */
    public function refresh(Request $request): array
    {
        $expiry = $request->get('expiry');

        $optionData = $this->getOptionChain($expiry);

        return [
            'success' => true,
            'expiry'  => $expiry,
            'data'    => $optionData,
        ];
    }

    /**
     * Main Option Chain Builder
     */
    public function getOptionChain(?string $expiry): array
    {
        $spot = $this->getNiftySpotPrice();

        $strikes = $this->generateStrikePrices($spot);

        return collect($strikes)
            ->map(function ($strike) {

                return [
                    'strike' => $strike,

                    'ce' => [
                        'ltp' => 0,
                        'oi'  => 0,
                        'iv'  => 0,
                    ],

                    'pe' => [
                        'ltp' => 0,
                        'oi'  => 0,
                        'iv'  => 0,
                    ],
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Nifty Spot
     */
    public function getNiftySpotPrice(): float
    {
        return Cache::remember(
            'nifty_spot_price',
            10,
            fn () => 25000
        );
    }

    /**
     * ATM ± Range
     */
    public function generateStrikePrices(
        float $spot,
        int $range = 20
    ): array {

        $atm = round($spot / 50) * 50;

        $strikes = [];

        for ($i = -$range; $i <= $range; $i++) {
            $strikes[] = $atm + ($i * 50);
        }

        return $strikes;
    }

    /**
     * Option symbol builder
     */
    public function buildOptionSymbol(
        string $expiry,
        int $strike,
        string $type
    ): string {

        return sprintf(
            'NIFTY%s%d%s',
            $expiry,
            $strike,
            strtoupper($type)
        );
    }

    /**
     * Greeks placeholder
     */
    public function getOptionGreeks(
        float $spot,
        float $strike,
        float $iv
    ): array {

        return [
            'delta' => 0,
            'gamma' => 0,
            'theta' => 0,
            'vega'  => 0,
            'iv'    => $iv,
        ];
    }
}