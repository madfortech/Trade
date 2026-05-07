<?php

namespace App\Services\Angel;

class NiftyHistoryService
{
    public function niftyPayload(): array
    {
        return [

            "exchange" => "NSE",

            "symboltoken" => "99926000",

            "interval" => "ONE_MINUTE",

            "fromdate" =>
                date(
                    'Y-m-d H:i',
                    strtotime("-2 days")
                ),

            "todate" =>
                date('Y-m-d H:i')
        ];
    }
}