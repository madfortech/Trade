<?php

namespace App\Http\Controllers\Nifty;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class NiftyChartController extends Controller
{
    protected string $baseUrl =
        'https://apiconnect.angelone.in';

    protected function headers(): array
    {
        return [

            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',

            'Authorization' =>
                'Bearer ' . Cache::get('angel_jwt'),

            'X-UserType'      => 'USER',
            'X-SourceID'      => 'WEB',

            'X-ClientLocalIP'  => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',

            'X-PrivateKey' =>
                env('ANGEL_API_KEY')
        ];
    }

    public function index(Request $request)
    {
        try {

            $payload = [

                "exchange" =>
                    $request->exchange,

                "symboltoken" =>
                    $request->token,

                "interval" =>
                    $request->interval,

                "fromdate" =>
                    now()
                        ->subDays(2)
                        ->format('Y-m-d H:i'),

                "todate" =>
                    now()
                        ->format('Y-m-d H:i'),
            ];

            $response = Http::withHeaders(
                $this->headers()
            )->post(

                $this->baseUrl .
                '/rest/secure/angelbroking/historical/v1/getCandleData',

                $payload
            );

            return response()->json(
                $response->json()
            );

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}