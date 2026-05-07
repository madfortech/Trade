<?php

namespace App\Services\Sensex;

class SensexChainBuilderService
{
    public function build(
        array $filteredOptions,
        array $quotes
    ): array {

        $quoteMap = [];

        foreach ($quotes as $item) {

            $quoteMap[
                (string)$item['symbolToken']
            ] = $item;
        }

        $optionsData = [];

        foreach ($filteredOptions as $option) {

            $token =
                $option['token'];

            if (
                !isset($quoteMap[$token])
            ) {
                continue;
            }

            $quote =
                $quoteMap[$token];

            $strike =
                $option['strike'];

            $type =
                $option['type'];

            $optionsData[$strike][$type] = [

                'ltp' =>
                    $quote['ltp'] ?? 0,

                'oi' =>
                    $quote['opnInterest'] ?? 0,

                'percentChange' =>
                    $quote['percentChange'] ?? 0,

                'symbol' =>
                    $option['symbol'],

                'symbol_token' =>
                    $token
            ];
        }

        ksort($optionsData);

        return $optionsData;
    }
}

