<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;

use App\Services\Angel\AngelApiService;
use App\Services\Angel\NiftyLiveService;

use Illuminate\Support\Facades\Cache;

class NiftyLiveController extends Controller
{
    public function __construct(
        private readonly AngelApiService $api,
        private readonly NiftyLiveService $live,
    ) {
    }

    public function index()
    {
        $token = Cache::get('angel_jwt');

        $response =
            $this->api->post(

                $token,

                '/rest/secure/angelbroking/market/v1/quote/',

                $this->live->payload()
            );

        return response()->json(
            $response->json()
        );
    }
}