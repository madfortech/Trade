<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AngelProfileController extends Controller
{
    private $baseUrl = "https://apiconnect.angelone.in";

    /**
     * Common Headers Helper
     */
    private function getHeaders($token)
    {
        return [
            'Authorization'   => 'Bearer ' . $token,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
            'X-UserType'      => 'USER',
            'X-SourceID'      => 'WEB',
            'X-ClientLocalIP' => '127.0.0.1',
            'X-ClientPublicIP'=> '127.0.0.1',
            'X-MACAddress'    => '00-00-00-00-00-00',
            'X-PrivateKey'    => trim(env('ANGEL_API_KEY')) // trim() zaroori hai spaces hatane ke liye
        ];
    }
   
    // Phase 1: Page Load (HTML)
    public function home() {
        $token = session('angel_jwt');
        if (!$token) return redirect()->route('angel.login')->with('error', 'Session Expired.');

        // Initial Data to prevent errors on first load
        $nifty = ['ltp' => '0.00', 'netChange' => '0.00', 'percentChange' => '0.00', 'high' => '---', 'low' => '---'];
        $sensex = $nifty;
        $crudeOil = ['ltp' => '0.00', 'netChange' => '0.00', 'percentChange' => '0.00', 'high' => '---', 'low' => '---'];

        $profileRes = Http::withHeaders($this->getHeaders($token))
            ->get($this->baseUrl . "/rest/secure/angelbroking/user/v1/getProfile");
        $profile = $profileRes->json()['data'] ?? ['name' => 'N/A', 'clientcode' => 'N/A'];

        return view('angel.home', compact('nifty', 'sensex', 'crudeOil', 'profile'));
    }

    // Phase 2: Live Updates (JSON)
    public function getMarketData() {
        $token = session('angel_jwt');
        if (!$token) return response()->json(['error' => 'Expired'], 401);

        $marketRes = Http::withHeaders($this->getHeaders($token))
            ->post($this->baseUrl . "/rest/secure/angelbroking/market/v1/quote", [
                "mode" => "FULL", 
                "exchangeTokens" => ["NSE" => ["26000"], "BSE" => ["99919000"], "MCX" => ["226702"]]
            ]);

        $data = $marketRes->json();
        

        if (isset($data['errorcode']) && $data['errorcode'] == "AB1004") {
            return response()->json(['error' => 'Invalid Session'], 401);
        }

        $output = ['nifty' => null, 'sensex' => null, 'crudeOil' => null];
        if (isset($data['data']['fetched'])) {
             foreach ($data['data']['fetched'] as $row) {
                $temp = [
                    'ltp' => number_format($row['ltp'], 2),
                    'change' => ($row['netChange'] > 0 ? '+' : '') . number_format($row['netChange'], 2),
                    'percent' => number_format($row['percentChange'], 2),
                    'high' => $row['high'] > 0 ? number_format($row['high'], 2) : '---',
                    'low' => $row['low'] > 0 ? number_format($row['low'], 2) : '---',
                    'color' => $row['netChange'] >= 0 ? 'text-green-600' : 'text-red-600'
                ];

                // Explicit check for each token
                if ($row['symbolToken'] == "26000") {
                    
                    $output['nifty'] = $temp;
                } elseif ($row['symbolToken'] == "99919000") {
                    $output['sensex'] = $temp;
                } elseif ($row['symbolToken'] == "226702") {
                    $output['crudeOil'] = $temp;
                }
            }
            

        }
        return response()->json($output);
    }

 
}