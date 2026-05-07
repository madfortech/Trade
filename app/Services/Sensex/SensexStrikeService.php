<?php

namespace App\Services\Sensex;

class SensexStrikeService
{
    public function extract(
        string $symbol
    ): ?int {

        preg_match(

            '/(\d{5})(CE|PE)$/',

            $symbol,

            $matches
        );

        if (
            !isset($matches[1])
        ) {
            return null;
        }

        return (int)$matches[1];
    }

    public function range(
        int $atm
    ): array {

        return [

            $atm - 1000,

            $atm + 1000
        ];
    }
}

