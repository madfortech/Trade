<?php

namespace App\Services\Sensex;

use Illuminate\Support\Facades\Cache;

class SensexScripService
{
    public function master(): array
    {
        return Cache::remember(

            'angel_scrip_master',

            3600,

            function () {

                return json_decode(

                    file_get_contents(

                        'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json'
                    ),

                    true
                );
            }
        );
    }
}

