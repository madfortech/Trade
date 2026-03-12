<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $google2fa = new Google2FA();
        $google2fa->setEnforceGoogleAuthenticatorCompatibility(false);

        // Direct key daal rahe hain test ke liye
        $secret = "KBUFFEP4QAVYBR6OPRPWZSYORI"; 
        
        // TOTP generate karein
        $totp = $google2fa->getCurrentOtp($secret);

        $response = Http::withHeaders([
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
            'X-UserType'        => 'USER',
            'X-SourceID'        => 'WEB',
            'X-ClientLocalIP'   => '127.0.0.1',
            'X-ClientPublicIP'  => '127.0.0.1',
            'X-MACAddress'      => '00-00-00-00-00-00',
            'X-PrivateKey'      => "ivXPeqhN" // Direct API Key
        ])->post($this->baseUrl . "/rest/auth/angelbroking/user/v1/loginByPassword", [
            'clientcode' => "JANAK4986",
            'password'   => "1989",
            'totp'       => $totp,
        ]);

        $resData = $response->json();

        if (isset($resData['status']) && $resData['status'] === true) {
            // Session save karna
            session([
                'angel_jwt' => $resData['data']['jwtToken'],
                'clientCode' => "JANAK4986"
            ]);
            session()->save(); 

            return redirect()->route('angel.home'); 
        }

        // Agar fail ho toh error screen par dikhega, loop nahi banega
        dd("Login Failed!", $resData, "TOTP used: " . $totp);

    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
}
}
