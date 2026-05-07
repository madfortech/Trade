<?php

namespace App\Http\Controllers\Nifty;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class NiftyOptionChainController extends Controller
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

            $response = Http::withHeaders(
                $this->headers()
            )->get(
                $this->baseUrl
            );

            return response()->json([
                'success' => true,
                'data' => $response->json()
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}