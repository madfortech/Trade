<?php

namespace App\Http\Controllers\Sensex;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Services\Sensex\SensexOptionDataService;

class SensexOptionChainController extends Controller
{
    public function __construct(
        private readonly SensexOptionDataService $service
    ) {
    }

    public function index(Request $request)
    {
        $data =
            $this->service
                ->get($request);

        /*
        |--------------------------------------------------------------------------
        | AJAX RESPONSE
        |--------------------------------------------------------------------------
        */

        if ($request->ajax()) {

            return response()->json(
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VIEW RESPONSE
        |--------------------------------------------------------------------------
        */

        return view(

            'sensex.option-data',

            [

                'optionsData' =>
                    $data['data'] ?? [],

                'sensexSpot' =>
                    $data['sensexSpot'] ?? 0,

                'atmStrike' =>
                    $data['atm'] ?? 0,

                'allExpiries' =>
                    $data['expiries'] ?? [],

                'selectedExpiry' =>
                    $data['selectedExpiry'] ?? null
            ]
        );
    }

    public function refresh(Request $request)
    {
        return response()->json(

            $this->service
                ->get($request)
        );
    }
}

