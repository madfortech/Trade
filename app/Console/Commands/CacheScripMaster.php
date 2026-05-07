<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheScripMaster extends Command
{
    protected $signature   = 'scrip:cache';
    protected $description = 'Download & cache Angel One scrip master (BFO SENSEX only)';

    /**
     * ✅ FIX: Angel scrip master stores expiry as "19MAR2026" format.
     * PHP's strtotime() cannot parse this — returns false.
     * We manually parse it here.
     */
    private function parseExpiry(string $expiry): int
    {
        // Format: 19MAR2026  or  19Mar2026
        $months = [
            'JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,
            'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12,
        ];

        $expiry = strtoupper(trim($expiry));

        // Try standard strtotime first (handles "2026-03-19" etc)
        $ts = strtotime($expiry);
        if ($ts && $ts > 0) return $ts;

        // Manual parse: "19MAR2026"
        if (preg_match('/^(\d{2})([A-Z]{3})(\d{4})$/', $expiry, $m)) {
            $day   = (int) $m[1];
            $month = $months[$m[2]] ?? 0;
            $year  = (int) $m[3];
            if ($month > 0) {
                return mktime(0, 0, 0, $month, $day, $year);
            }
        }

        return 0;
    }

    /**
     * ✅ FIX: Convert expiry to consistent "ddMMMYYYY" uppercase format
     * e.g. "19MAR2026" so cache keys are consistent with controller lookups
     */
    private function formatExpiry(string $expiry): string
    {
        return strtoupper(trim($expiry));
    }

    public function handle(): int
    {
        $this->info('Downloading scrip master...');

        $url = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';

        $ctx = stream_context_create(['http' => ['timeout' => 120]]);
        $raw = @file_get_contents($url, false, $ctx);

        if (!$raw) {
            $this->error('Download failed!');
            return 1;
        }

        $this->info('Parsing ' . round(strlen($raw) / 1024 / 1024, 1) . ' MB...');
        $all = json_decode($raw, true);
        unset($raw);

        if (!$all) {
            $this->error('JSON parse failed!');
            return 1;
        }

        $this->info('Total records: ' . count($all));

        // ── Extract ONLY BFO SENSEX records ──────────────────────────
        $sensex  = [];
        $todayTs = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y'));

        foreach ($all as $s) {
            if (
                strtoupper($s['name']     ?? '') === 'SENSEX' &&
                strtoupper($s['exch_seg'] ?? '') === 'BFO'    &&
                !empty($s['expiry'])
            ) {
                // ✅ FIX: use our parser instead of strtotime()
                $expiryTs = $this->parseExpiry($s['expiry']);

                if ($expiryTs < $todayTs) continue; // skip expired

                $sensex[] = [
                    'token'    => $s['token'],
                    'symbol'   => $s['symbol'],
                    'expiry'   => $this->formatExpiry($s['expiry']),
                    'expiry_ts'=> $expiryTs,
                    'strike'   => (float) ($s['strike'] ?? 0),
                    'exch_seg' => $s['exch_seg'],
                ];
            }
        }
        unset($all);

        $this->info('SENSEX BFO records found: ' . count($sensex));

        if (empty($sensex)) {
            $this->error('No SENSEX records found — check scrip master format!');
            return 1;
        }

        // ── Expiry list (sorted ascending) ───────────────────────────
        $expiryMap = [];
        foreach ($sensex as $s) {
            $expiryMap[$s['expiry']] = $s['expiry_ts'];
        }
        asort($expiryMap); // sort by timestamp
        $expiries = array_keys($expiryMap);
        $expiries = array_slice($expiries, 0, 12);

        $this->info('Expiries found: ' . implode(', ', $expiries));

        // ── Strike-token map per expiry ───────────────────────────────
        $maps = [];
        foreach ($expiries as $exp) {
            $map = [];
            foreach ($sensex as $s) {
                if ($s['expiry'] !== $exp) continue;

                $sym = strtoupper($s['symbol']);

                // ✅ FIX: Angel stores strike*100, divide to get real strike
                $strike = (int) round($s['strike'] / 100);
                if ($strike <= 0) continue;

                if (str_ends_with($sym, 'CE')) $map[$strike]['ce'] = $s['token'];
                elseif (str_ends_with($sym, 'PE')) $map[$strike]['pe'] = $s['token'];
            }
            ksort($map);
            $maps[$exp] = $map;

            $this->info("  {$exp}: " . count($map) . " strikes, sample: " . implode(', ', array_slice(array_keys($map), 0, 5)));
        }

        // ── Cache everything ──────────────────────────────────────────
        Cache::put('sensex_expiry_list_v4', $expiries, 86400);
        Cache::put('sensex_strike_maps_v4', $maps,     86400);

        foreach ($maps as $exp => $map) {
            Cache::put("sensex_map_{$exp}", $map, 86400);
        }

        $this->info('✅ Cache stored! Expiries: ' . implode(', ', $expiries));

        // Show sample strikes for first expiry near spot
        $firstExp = $expiries[0] ?? null;
        if ($firstExp && !empty($maps[$firstExp])) {
            $strikes = array_keys($maps[$firstExp]);
            $this->info('First expiry ' . $firstExp . ' strikes (first 10): ' . implode(', ', array_slice($strikes, 0, 10)));
            $this->info('First expiry ' . $firstExp . ' total strikes: ' . count($strikes));
        }

        return 0;
    }
}
