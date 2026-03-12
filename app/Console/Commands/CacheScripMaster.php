<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheScripMaster extends Command
{
    protected $signature   = 'scrip:cache';
    protected $description = 'Download & cache Angel One scrip master (BFO SENSEX only)';

    public function handle(): int
    {
        $this->info('Downloading scrip master...');

        $url = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';

        // ── Stream download — low memory ──────────────────────────────────
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

        // ── Extract ONLY BFO SENSEX records ───────────────────────────────
        $sensex = [];
        $today  = strtotime(date('Y-m-d'));

        foreach ($all as $s) {
            if (
                strtoupper($s['name']     ?? '') === 'SENSEX' &&
                strtoupper($s['exch_seg'] ?? '') === 'BFO'    &&
                !empty($s['expiry'])
                && strtotime($s['expiry']) >= $today
            ) {
                $sensex[] = [
                    'token'   => $s['token'],
                    'symbol'  => $s['symbol'],
                    'expiry'  => $s['expiry'],
                    'strike'  => (float) $s['strike'],   // stored as strike*100
                    'exch_seg'=> $s['exch_seg'],
                ];
            }
        }
        unset($all);

        $this->info('SENSEX BFO records: ' . count($sensex));

        // ── Expiry list ───────────────────────────────────────────────────
        $expiries = [];
        foreach ($sensex as $s) $expiries[$s['expiry']] = true;
        $expiries = array_keys($expiries);
        usort($expiries, fn($a, $b) => strtotime($a) - strtotime($b));
        $expiries = array_slice($expiries, 0, 12);

        // ── Strike-token map per expiry ───────────────────────────────────
        $maps = [];
        foreach ($expiries as $exp) {
            $map = [];
            foreach ($sensex as $s) {
                if (strtoupper($s['expiry']) !== strtoupper($exp)) continue;
                $sym    = strtoupper($s['symbol']);
                $strike = (int) round($s['strike'] / 100); // ← KEY: divide by 100
                if ($strike <= 0) continue;

                if (str_ends_with($sym, 'CE')) $map[$strike]['ce'] = $s['token'];
                if (str_ends_with($sym, 'PE')) $map[$strike]['pe'] = $s['token'];
            }
            ksort($map);
            $maps[$exp] = $map;
        }

        // ── Cache everything ──────────────────────────────────────────────
        Cache::put('sensex_expiry_list_v4',  $expiries, 86400);
        Cache::put('sensex_strike_maps_v4',  $maps,     86400);

        // Also cache per-expiry for fast lookup
        foreach ($maps as $exp => $map) {
            Cache::put("sensex_map_{$exp}", $map, 86400);
        }

        $this->info('Cached! Expiries: ' . implode(', ', $expiries));
        $this->info('Sample strikes for ' . ($expiries[0] ?? '') . ': ' . implode(', ', array_slice(array_keys($maps[$expiries[0]] ?? []), 0, 5)));

        return 0;
    }
}
