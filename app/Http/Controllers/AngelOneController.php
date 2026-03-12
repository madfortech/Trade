<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class AngelOneController extends Controller
{
    private $baseUrl = "https://apiconnect.angelone.in";

    private function getHeaders($token = null)
    {
        $headers = [
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
            'X-UserType'      => 'USER',
            'X-SourceID'      => 'WEB',
            'X-ClientLocalIP' => '127.0.0.1',
            'X-ClientPublicIP'=> '127.0.0.1',
            'X-MACAddress'    => '00-00-00-00-00-00',
            'X-PrivateKey'    => env('ANGEL_API_KEY', 'ivXPeqhN')
        ];
        if ($token) { $headers['Authorization'] = 'Bearer ' . $token; }
        return $headers;
    }

    // public function login()
    // {
    //     try {
    //         $google2fa = new Google2FA();
    //         $google2fa->setEnforceGoogleAuthenticatorCompatibility(false);
    //         $secret = strtoupper(trim(env('ANGEL_TOTP_SECRET', 'KBUFFEP4QAVYBR6OPRPWZSYORI')));
    //         $totp = $google2fa->getCurrentOtp($secret);

    //         $response = Http::withHeaders($this->getHeaders())
    //             ->post($this->baseUrl . "/rest/auth/angelbroking/user/v1/loginByPassword", [
    //                 'clientcode' => env('ANGEL_CLIENT_ID', 'JANAK4986'),
    //                 'password'   => env('ANGEL_PASSWORD', '1989'),
    //                 'totp'       => $totp,
    //             ]);

    //         $resData = $response->json();

    //         // ABHI KE LIYE: Redirect ke bajaye JSON print karein
    //         // Isse aapko browser mein saaf dikhega ki Token mil raha hai ya nahi
    //         return response()->json([
    //             'api_response' => $resData,
    //             'current_totp' => $totp,
    //             'cache_driver' => config('cache.default')
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }


    // public function getProfile()
    // {
    //     // Cache se token uthayein
    //     $token = Cache::get('angel_jwt');

    //     if (!$token) {
    //         return response()->json(['error' => 'Token not found in cache. Please login again.'], 401);
    //     }

    //     $response = Http::withHeaders($this->getHeaders($token))
    //         ->get($this->baseUrl . "/rest/secure/angelbroking/user/v1/getProfile");

    //     return $response->json();
    // }

}