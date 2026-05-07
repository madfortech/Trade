<?php

namespace App\Services\Angel;

class AngelHeaderService
{
    public function headers(string $token): array
    {
        return [

            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',

            'Authorization'   => 'Bearer ' . $token,

            'X-UserType'      => 'USER',
            'X-SourceID'      => 'WEB',

            'X-ClientLocalIP'  => '127.0.0.1',
            'X-ClientPublicIP' => '127.0.0.1',

            'X-PrivateKey' =>
                env('ANGEL_API_KEY')
        ];
    }
}