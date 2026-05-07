<?php

namespace App\Services\Angel;

use Illuminate\Support\Facades\Http;

class AngelApiService
{
    protected string $baseUrl =
        'https://apiconnect.angelone.in';

    public function __construct(
        private readonly AngelHeaderService $headers
    ) {
    }

    public function post(
        string $token,
        string $endpoint,
        array $payload
    ) {

        return Http::withHeaders(

            $this->headers
                ->headers($token)

        )->post(

            $this->baseUrl . $endpoint,

            $payload
        );
    }
}