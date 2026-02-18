<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class AngelLoginController extends Controller
{
    private $baseUrl = "https://apiconnect.angelone.in";

    /**
     * Helper to get request headers
     */
    private function getHeaders(Request $request)
    {
        return [
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
            'X-UserType'        => 'USER',
            'X-SourceID'        => 'WEB',
            'X-ClientLocalIP'   => $request->ip() ?? '127.0.0.1',
            'X-ClientPublicIP'  => $request->ip() ?? '127.0.0.1',
            'X-MACAddress'      => '00-00-00-00-00-00', // Consider making this dynamic if possible
            'X-PrivateKey'      => env('ANGEL_API_KEY')
        ];
    }

    public function login(Request $request)
    {
        try {
            // 1. Generate TOTP
            $google2fa = new Google2FA();
            $google2fa->setEnforceGoogleAuthenticatorCompatibility(false);

            $secret = env('ANGEL_TOTP_SECRET');
            $secret = strtoupper(str_replace([' ', '-'], '', $secret));
            $totp = $google2fa->getCurrentOtp($secret);

            // 2. API Request
            $response = Http::withHeaders($this->getHeaders($request))
                ->post($this->baseUrl . "/rest/auth/angelbroking/user/v1/loginByPassword", [
                    'clientcode' => env('ANGEL_CLIENT_ID'),
                    'password'   => env('ANGEL_PASSWORD'),
                    'totp'       => $totp,
                ]);

            $resData = $response->json();

            // 3. Handle Success
            if (isset($resData['status']) && $resData['status'] === true) {
                $data = $resData['data'];
                $clientCode = env('ANGEL_CLIENT_ID');

                // Consistent Storage for Middleware
                // We store in session for the user's specific browser tab
                session([
                    'angel_jwt'   => $data['jwtToken'],
                    'feedToken'   => $data['feedToken'],
                    'clientCode'  => $clientCode,
                    'angel_profile' => $data
                ]);

                // Store in Cache as a backup/global reference (User-Specific Key)
                $cacheKey = "angel_session_" . $clientCode;
                Cache::put($cacheKey, $data['jwtToken'], now()->addHours(12));
                
                return redirect()->route('angel.home')->with('success', 'Angel One connected successfully!'); 
            }

            // 4. Handle API Logic Failures
            return redirect()->back()->with('error', 'Angel API Error: ' . ($resData['message'] ?? 'Check credentials'));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Login Exception: ' . $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        $clientCode = session('clientCode');
        
        if($clientCode) {
            Cache::forget("angel_session_" . $clientCode);
        }
        
        $request->session()->forget(['angel_jwt', 'feedToken', 'clientCode', 'angel_profile']);
        
        return redirect()->route('angel.login')->with('success', 'Angel One Disconnected');
    }
}