<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;

use App\Services\Angel\AngelApiService;
use App\Services\Angel\NiftyHistoryService;

use Illuminate\Support\Facades\Cache;

class NiftyHistoryController extends Controller
{
    public function __construct(
        private readonly AngelApiService $api,
        private readonly NiftyHistoryService $history,
    ) {
    }

    public function index()
    {
        try {

            $token = Cache::get('angel_jwt');

            if (!$token) {

                return response()->json([

                    'status' => false,

                    'message' =>
                        'Please login again'
                ]);
            }

            $response =
                $this->api->post(

                    $token,

                    '/rest/secure/angelbroking/historical/v1/getCandleData',

                    $this->history->payload()
                );

            return response()->json(
                $response->json()
            );

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,

                'message' =>
                    $e->getMessage()
            ]);
        }
    }
}