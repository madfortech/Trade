<?php

namespace App\Services;

use Carbon\Carbon;

class ExpiryService
{
    /**
     * Get unique expiry list from scrip master.
     */
    public function getAvailableExpiries(array $scripMaster): array
    {
        $expiries = [];

        foreach ($scripMaster as $row) {

            if (!isset($row['expiry'])) {
                continue;
            }

            $expiry = trim((string) $row['expiry']);

            if ($expiry === '') {
                continue;
            }

            $expiries[] = $expiry;
        }

        $expiries = array_unique($expiries);

        usort($expiries, function ($a, $b) {
            return strtotime($a) <=> strtotime($b);
        });

        return array_values($expiries);
    }

    /**
     * Get selected expiry.
     */
    public function getSelectedExpiry(
        array $availableExpiries,
        ?string $requestedExpiry = null
    ): ?string {

        if (
            $requestedExpiry &&
            in_array($requestedExpiry, $availableExpiries)
        ) {
            return $requestedExpiry;
        }

        return $availableExpiries[0] ?? null;
    }

    /**
     * Get nearest future expiry.
     */
    public function getNearestExpiry(array $availableExpiries): ?string
    {
        $today = Carbon::today();

        foreach ($availableExpiries as $expiry) {

            try {

                $expiryDate = Carbon::parse($expiry);

                if ($expiryDate->gte($today)) {
                    return $expiry;
                }

            } catch (\Throwable $e) {
                continue;
            }
        }

        return $availableExpiries[0] ?? null;
    }

    /**
     * Check market status.
     */
    public function isMarketOpen(): bool
    {
        $now = Carbon::now('Asia/Kolkata');

        $hhmm = (int) $now->format('Hi');

        return $now->isWeekday()
            && $hhmm >= 915
            && $hhmm <= 1530;
    }

    /**
     * Last trading day.
     */
    public function getLastTradingDay(): Carbon
    {
        $date = Carbon::now('Asia/Kolkata');

        while ($date->isWeekend()) {
            $date->subDay();
        }

        return $date;
    }
}