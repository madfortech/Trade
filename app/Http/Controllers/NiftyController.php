<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NiftyController extends Controller
{
 
    // NiftyController.php
    public function chart() {
        // Session se data nikaalein
        $clientCode = session('clientCode');
        $feedToken = session('feedToken');
        $apiKey = env('ANGEL_API_KEY');

        // DEBUG: Agar ye page khali aa raha hai, toh ise uncomment karke check karein
     //   dd(session()->all()); 

        return view('trading.nifty', [ // Apni blade file ka sahi path dein
            'clientCode' => $clientCode,
            'feedToken'  => $feedToken,
            'apiKey'     => $apiKey,
            'profile'    => session('profile')
        ]);
    }
        
}
