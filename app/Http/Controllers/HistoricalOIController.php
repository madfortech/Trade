<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class HistoricalOIController extends Controller {
    private $baseUrl = "https://apiconnect.angelone.in";

    public function index() {
        if (!Cache::has('angel_jwt')) {
            return redirect('/angel/login')->with('error', 'Login to Angel One first.');
        }
        return view('dashboard');
    }

    private function getHeaders($token) {
        return [
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
            'Authorization'   => 'Bearer ' . $token,
            'X-UserType'      => 'USER',
            'X-SourceID'      => 'WEB',
            'X-ClientLocalIP' => '127.0.0.1',
            'X-ClientPublicIP'=> '127.0.0.1',
            'X-PrivateKey'    => env('ANGEL_API_KEY')
        ];
    }

    // LIVE DATA
    public function fetchNiftyData() {
        $token = Cache::get('angel_jwt');
        $payload = ["mode" => "FULL", "exchangeTokens" => ["NSE" => ["99926000"]]];
        $response = Http::withHeaders($this->getHeaders($token))
            ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote/", $payload);
        return $response->json();
    }

    // HISTORICAL DATA
   // HistoricalOIController.php
    public function fetchNiftyHistory() {
        $token = Cache::get('angel_jwt');
        
        // Check karein ki token hai ya nahi
        if (!$token) {
            return response()->json(['status' => false, 'message' => 'Token not found in cache. Please login again.']);
        }

        $payload = [
            "exchange" => "NSE",
            "symboltoken" => "99926000", // Nifty 50
            "interval" => "ONE_MINUTE",
            "fromdate" => date('Y-m-d H:i', strtotime("-2 days")),
            "todate" => date('Y-m-d H:i')
        ];

        try {
            $response = Http::withHeaders($this->getHeaders($token))
                ->post("https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getCandleData", $payload);

            // Agar response 200 nahi hai toh error return karein
            if ($response->failed()) {
                return response()->json(['status' => false, 'message' => 'API Error', 'details' => $response->body()]);
            }

            return $response->json();
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
}
}