<?php

namespace App\Services\AIAnalysis;

class CandleAnalysisService
{
    public function calcSupportResistance(array $candles): array
    {
        if (empty($candles)) {
            return ['support' => null, 'resistance' => null, 'current' => null];
        }

        $recent = [];
        foreach (array_slice($candles, -50) as $c) {
            $n = $this->normalizeCandle($c);
            if ($n && $n['cl'] > 0) {
                $recent[] = $n;
            }
        }

        if (empty($recent)) {
            return ['support' => null, 'resistance' => null, 'current' => null];
        }

        $last = end($recent);
        $current = $last['cl'];
        $len = count($recent);

        $swingHighs = [];
        $swingLows = [];

        for ($i = 2; $i < $len - 2; $i++) {
            $h = $recent[$i]['h'];
            $l = $recent[$i]['l'];
            $h1 = $recent[$i - 1]['h'];
            $h2 = $recent[$i - 2]['h'];
            $h3 = $recent[$i + 1]['h'];
            $h4 = $recent[$i + 2]['h'];
            $l1 = $recent[$i - 1]['l'];
            $l2 = $recent[$i - 2]['l'];
            $l3 = $recent[$i + 1]['l'];
            $l4 = $recent[$i + 2]['l'];

            if ($h > 0 && $h > $h1 && $h > $h2 && $h > $h3 && $h > $h4) {
                $swingHighs[] = $h;
            }
            if ($l > 0 && $l < $l1 && $l < $l2 && $l < $l3 && $l < $l4) {
                $swingLows[] = $l;
            }
        }

        $resistance = null;
        if (!empty($swingHighs)) {
            $above = array_filter($swingHighs, fn ($v) => $v > $current);
            $resistance = !empty($above) ? min($above) : max($swingHighs);
        }
        if (!$resistance) {
            $allHighs = array_filter(array_column($recent, 'h'), fn ($v) => $v > 0);
            $resistance = !empty($allHighs) ? max($allHighs) : null;
        }

        $support = null;
        if (!empty($swingLows)) {
            $below = array_filter($swingLows, fn ($v) => $v < $current);
            $support = !empty($below) ? max($below) : min($swingLows);
        }
        if (!$support) {
            $allLows = array_filter(array_column($recent, 'l'), fn ($v) => $v > 0);
            $support = !empty($allLows) ? min($allLows) : null;
        }

        return [
            'support' => $support ? round($support, 2) : null,
            'resistance' => $resistance ? round($resistance, 2) : null,
            'current' => $current ? round($current, 2) : null,
        ];
    }

    public function buildCandleSummary(array $candles): string
    {
        if (empty($candles)) {
            return '(No candle data provided)';
        }

        $last = array_slice($candles, -15);
        $lines = ['=== RECENT CANDLES (last ' . count($last) . ', format: timestamp O H L C V) ==='];

        foreach ($last as $c) {
            $n = $this->normalizeCandle($c);
            if (!$n || $n['o'] <= 0) {
                continue;
            }

            $dir = $n['cl'] >= $n['o'] ? '🟢' : '🔴';
            $lines[] = "{$dir} {$n['ts']}  O:{$n['o']}  H:{$n['h']}  L:{$n['l']}  C:{$n['cl']}  V:{$n['vol']}";
        }

        $closes = [];
        foreach ($candles as $c) {
            $n = $this->normalizeCandle($c);
            if ($n && $n['cl'] > 0) {
                $closes[] = $n['cl'];
            }
        }

        if (count($closes) >= 2) {
            $first = $closes[0];
            $lastC = end($closes);
            $change = round((($lastC - $first) / $first) * 100, 2);
            $lines[] = "=== TOTAL PERIOD: Start {$first} → Current {$lastC} | Change: {$change}% ===";
        }

        return implode("\n", $lines);
    }

    public function formatLevel(float|int|null $value, string $fallback = '—'): string
    {
        return $value ? number_format($value, 2) : $fallback;
    }

    private function normalizeCandle(mixed $c): ?array
    {
        if (!is_array($c)) {
            return null;
        }

        if (isset($c['open'])) {
            return [
                'ts' => $c['time'] ?? '—',
                'o' => (float) ($c['open'] ?? 0),
                'h' => (float) ($c['high'] ?? 0),
                'l' => (float) ($c['low'] ?? 0),
                'cl' => (float) ($c['close'] ?? 0),
                'vol' => $c['volume'] ?? '—',
            ];
        }

        if (isset($c[0])) {
            return [
                'ts' => $c[0] ?? '—',
                'o' => (float) ($c[1] ?? 0),
                'h' => (float) ($c[2] ?? 0),
                'l' => (float) ($c[3] ?? 0),
                'cl' => (float) ($c[4] ?? 0),
                'vol' => $c[5] ?? '—',
            ];
        }

        return null;
    }
}
