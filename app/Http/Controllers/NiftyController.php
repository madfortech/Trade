<?php
// delete this file after testing
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithNiftyOptionChain;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NiftyController extends Controller
{
    use InteractsWithNiftyOptionChain;

    private string $baseUrl = 'https://apiconnect.angelone.in';

    public function chart()
    {
        return view('trading.partials.nifty-terminal', [
            'clientCode' => session('clientCode'),
            'feedToken'  => session('feedToken'),
            'apiKey'     => env('ANGEL_API_KEY'),
            'profile'    => session('profile'),
        ]);
    }

    /**
     * EXACT LIVE DATA: High, Low, LTP, Volume
     */
    public function getLiveLtp()
    {
        $token = session('angel_jwt');
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Session expired. Please login again.'], 401);
        }

        try {
            $headers = $this->prepareHeaders($token);

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($this->baseUrl . '/rest/secure/angelbroking/market/v1/quote/', [
                    "mode" => "FULL", 
                    "exchangeTokens" => [
                        "NSE" => ["99926000"] // NIFTY 50 Token
                    ]
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'AngelOne API Error',
                    'status'  => $response->status()
                ], $response->status());
            }

            $result = $response->json();

            if (isset($result['data']['fetched'][0])) {
                $data = $result['data']['fetched'][0];
                return response()->json([
                    'success' => true,
                    'ltp'     => (float)($data['ltp'] ?? 0),
                    'high'    => (float)($data['high'] ?? 0),
                    'low'     => (float)($data['low'] ?? 0),
                    'volume'  => (int)($data['volume'] ?? 0),
                    'change'  => (float)($data['netChange'] ?? 0),
                    'percent' => (float)($data['percentChange'] ?? 0),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'No data found'], 404);

        } catch (\Exception $e) {
            Log::error("Nifty Live Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * HISTORICAL DATA
     */
    public function historicalData(Request $request) 
        {
            // Sabse important: Timezone ko Asia/Kolkata set karein
            date_default_timezone_set('Asia/Kolkata');

            $token = session('angel_jwt');
            if (!$token) return response()->json(['success' => false], 401);

            $interval = $request->get('interval', '5m');
            $intervalMap = [
                '5m'  => 'FIVE_MINUTE',
                '1h'  => 'ONE_HOUR',
                '1d'  => 'ONE_DAY'
            ];
            
            $apiInterval = $intervalMap[$interval] ?? 'FIVE_MINUTE';
            $toDate = date('Y-m-d H:i'); 
            $daysBack = ($interval === '1d') ? 100 : 7; 
            $fromDate = date('Y-m-d 09:15', strtotime("-$daysBack days"));

            try {
                $response = Http::withHeaders($this->prepareHeaders($token))
                    ->post($this->baseUrl . '/rest/secure/angelbroking/historical/v1/getCandleData', [
                        "exchange"    => "NSE",
                        "symboltoken" => "99926000", 
                        "interval"    => $apiInterval,
                        "fromdate"    => $fromDate,
                        "todate"      => $toDate
                    ]);

                $result = $response->json();

                if (isset($result['data']) && is_array($result['data'])) {
                    $formatted = [];
                    foreach ($result['data'] as $c) {
                        $formatted[] = [
                            // Timestamp ko Unix seconds mein convert karein
                            'time'  => strtotime($c[0]), 
                            'open'  => (float)$c[1],
                            'high'  => (float)$c[2],
                            'low'   => (float)$c[3],
                            'close' => (float)$c[4],
                            'vol'   => (int)$c[5]
                        ];
                    }
                    usort($formatted, fn($a, $b) => $a['time'] <=> $b['time']);

                    return response()->json(['success' => true, 'candles' => $formatted]);
                }
                return response()->json(['success' => false, 'message' => 'No data']);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

    private function prepareHeaders($token) 
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-UserType'    => 'USER',
            'X-SourceID'    => 'WEB',
            'X-ClientLocalIP'  => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',
            'X-MACAddress'     => '00-00-00-00-00-00',
            'X-PrivateKey'     => env('ANGEL_API_KEY'),
        ];
    }
}