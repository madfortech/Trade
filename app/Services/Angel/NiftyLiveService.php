<?php

namespace App\Services\Angel;

class NiftyLiveService
{
    public function payload(): array
    {
        return [

            "mode" => "FULL",

            "exchangeTokens" => [
                "NSE" => ["99926000"]
            ]
        ];
    }
}