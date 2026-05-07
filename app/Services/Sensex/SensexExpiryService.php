<?php

namespace App\Services\Sensex;

use Carbon\Carbon;

class SensexExpiryService
{
    public function get(
        array $scripMaster
    ): array {

        $expiries = [];

        $today =
            now()->startOfDay();

        foreach ($scripMaster as $item) {

            if (
                strtoupper(
                    $item['name'] ?? ''
                ) !== 'SENSEX'
            ) {
                continue;
            }

            if (
                strtoupper(
                    $item['exch_seg'] ?? ''
                ) !== 'BFO'
            ) {
                continue;
            }

            $expiry =
                trim(
                    $item['expiry'] ?? ''
                );

            if (!$expiry) {
                continue;
            }

            try {

                $date =
                    Carbon::createFromFormat(
                        'dMY',
                        strtoupper($expiry)
                    );

                if (
                    $date->lt($today)
                ) {
                    continue;
                }

                $expiries[] =
                    $expiry;

            } catch (\Exception $e) {
            }
        }

        $expiries =
            array_values(
                array_unique(
                    $expiries
                )
            );

        usort(

            $expiries,

            fn ($a, $b) =>

            Carbon::createFromFormat(
                'dMY',
                strtoupper($a)
            )->timestamp

            <=>

            Carbon::createFromFormat(
                'dMY',
                strtoupper($b)
            )->timestamp
        );

        return $expiries;
    }
}

