<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAnalysisController extends Controller
{
    private string $apiKey;
    private string $model   = 'llama-3.3-70b-versatile';
    private string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY', '');
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/nifty-ai-analyze  — Nifty Chart page
    // ════════════════════════════════════════════════════════════════════════
    public function niftyAnalyze(Request $request)
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('message', 500);
            }

            $interval      = $request->input('interval', '5m');
            $candles       = $request->input('candles', []);
            $candleSummary = $this->_buildCandleSummary($candles);

            // PHP side pe support/resistance calculate karo — AI pe depend mat karo
            $levels       = $this->formattedLevels($candles, 'N/A');
            $supportStr   = $levels['support'];
            $resistStr    = $levels['resistance'];
            $currentPrice = $levels['current'];

            $systemPrompt = <<<SYS
You are an expert NIFTY 50 index trader and technical analyst.
You MUST return ONLY a valid JSON object — no markdown, no backticks, no explanation, no text outside JSON.
Base your entire analysis STRICTLY on the candle data (OHLCV) provided. Do NOT invent or use placeholder values.
SYS;

            $userPrompt = <<<PROMPT
Analyze this NIFTY 50 candlestick data and provide a real, data-driven trading analysis.

Interval: {$interval}
Current Price: {$currentPrice}
Calculated Support Level: {$supportStr}
Calculated Resistance Level: {$resistStr}

{$candleSummary}

Rules:
1. verdict = "bullish", "bearish", or "sideways" based on actual price action
2. keyLevels.support MUST equal exactly: {$supportStr}
3. keyLevels.resistance MUST equal exactly: {$resistStr}
4. reasons = 5 specific observations from the candle data above (price movement, recent highs/lows, volume, trend direction). ZERO generic text allowed.
5. confidence % should reflect actual signal strength from the data

Return ONLY this JSON:
{
  "verdict": "",
  "icon": "🟢 or 🔴 or 🟡",
  "title": "SHORT CAPS TITLE",
  "confidence": "e.g. Medium Confidence (65%)",
  "trendAlign": "e.g. ✅ Bullish or ⚠ Mixed",
  "trendAlignColor": "#4ade80 or #f87171 or #facc15",
  "momentum": "e.g. 🟢 Strong Upside",
  "momentumColor": "#4ade80 or #f87171 or #facc15",
  "volSig": "e.g. ✅ Volume Confirming",
  "volSigColor": "#4ade80 or #f87171 or #facc15",
  "risk": "🟢 LOW or 🟡 MEDIUM or 🔴 HIGH",
  "riskColor": "#4ade80 or #facc15 or #f87171",
  "keyLevels": {
    "support": "{$supportStr}",
    "resistance": "{$resistStr}"
  },
  "reasons": [
    "Candle-based reason 1",
    "Candle-based reason 2",
    "Candle-based reason 3",
    "Candle-based reason 4",
    "Candle-based reason 5"
  ]
}
PROMPT;

            $data = $this->_callGroq($systemPrompt, $userPrompt);

            // Force correct S/R values regardless of what AI returned
            $data['keyLevels'] = [
                'support'    => $supportStr,
                'resistance' => $resistStr,
            ];

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('niftyAnalyze', $e, 'message');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/nifty-chat  — Nifty Chart page chat
    // ════════════════════════════════════════════════════════════════════════
    public function niftyChat(Request $request)
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('reply');
            }

            $message       = $request->input('message', '');
            $interval      = $request->input('interval', '5m');
            $candles       = $request->input('candles', []);
            $candleSummary = $this->_buildCandleSummary($candles);

            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho!']);
            }

            $levels       = $this->formattedLevels($candles);
            $currentPrice = $levels['current'];
            $support      = $levels['support'];
            $resistance   = $levels['resistance'];

            $systemPrompt = <<<SYS
You are an expert NIFTY 50 trader. Reply in Hinglish (Hindi+English mix), 3-5 lines max.
HTML allowed: <b>, <br>, <span style='color:#...'>.
Be direct and specific — use the REAL price numbers from the data provided. No generic advice.
SYS;

            $userPrompt = <<<PROMPT
Interval: {$interval} | Current Price: {$currentPrice}
Support: {$support} | Resistance: {$resistance}
{$candleSummary}
Question: {$message}
PROMPT;

            $reply = $this->_callGroqText($systemPrompt, $userPrompt);
            return response()->json(['success' => true, 'reply' => $reply]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('niftyChat', $e, 'reply', false);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/ai-analyze  — Option Chain chart modal
    // ════════════════════════════════════════════════════════════════════════
    public function analyze(Request $request)
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('message', 500);
            }

            $strike        = $request->input('strike');
            $side          = $request->input('side');
            $label         = $request->input('label', 'NIFTY Option');
            $source        = $request->input('source', 'Manual');
            $candles       = $request->input('candles', []);
            $candleSummary = $this->_buildCandleSummary($candles);

            $levels       = $this->formattedLevels($candles);
            $currentPrice = $levels['current'];
            $support      = $levels['support'];
            $resistance   = $levels['resistance'];

            $systemPrompt = "You are an expert NSE options trader. Return ONLY valid JSON — no markdown, no backticks. Base analysis strictly on provided candle data.";

            $userPrompt = <<<PROMPT
Analyze this option chart for a real trading decision.

Symbol: {$label} | Strike: {$strike} | Side: {$side} | Trigger: {$source}
Current Price: {$currentPrice} | Support: {$support} | Resistance: {$resistance}

{$candleSummary}

Return ONLY this JSON (real values from candle data, zero generic text in reasons):
{
  "verdict": "bullish|bearish|sideways",
  "icon": "🟢|🔴|🟡",
  "title": "SHORT CAPS TITLE",
  "confidence": "confidence with %",
  "trendAlign": "trend description",
  "trendAlignColor": "#hex",
  "momentum": "momentum description",
  "momentumColor": "#hex",
  "volSig": "volume signal",
  "volSigColor": "#hex",
  "risk": "🟢 LOW|🟡 MEDIUM|🔴 HIGH",
  "riskColor": "#hex",
  "reasons": ["5 specific candle-based observations — no generic text"]
}
PROMPT;

            $data = $this->_callGroq($systemPrompt, $userPrompt);
            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('analyze', $e, 'message');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/chart-chat
    // ════════════════════════════════════════════════════════════════════════
    public function chartChat(Request $request)
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('reply');
            }

            $message       = $request->input('message', '');
            $strike        = $request->input('strike');
            $side          = $request->input('side');
            $label         = $request->input('label', 'NIFTY Option');
            $context       = $request->input('context', []);
            $candles       = $context['candles'] ?? [];
            $candleSummary = $this->_buildCandleSummary($candles);

            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho bhai!']);
            }

            $levels       = $this->formattedLevels($candles);
            $currentPrice = $levels['current'];
            $support      = $levels['support'];
            $resistance   = $levels['resistance'];

            $systemPrompt = "You are an expert NSE options trader. Reply in Hinglish (Hindi+English), 3-5 lines. HTML: <b>, <br>. Use real price numbers from data. Answer directly.";

            $userPrompt = <<<PROMPT
Symbol: {$label} | Strike: {$strike} | Side: {$side}
Current: {$currentPrice} | Support: {$support} | Resistance: {$resistance}
{$candleSummary}
Question: {$message}
PROMPT;

            $reply = $this->_callGroqText($systemPrompt, $userPrompt);
            return response()->json(['success' => true, 'reply' => $reply]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('chartChat', $e, 'reply', false);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/sensex-ai-analyze
    // ════════════════════════════════════════════════════════════════════════
    public function sensexAnalyze(Request $request): JsonResponse
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('message', 500);
            }

            $label         = $request->input('label', 'SENSEX Option');
            $strike        = $request->input('strike');
            $side          = $request->input('side');
            $candles       = $request->input('candles', []);
            $candleSummary = $this->_buildCandleSummary($candles);
            $interval      = $request->input('interval', '5m');
            $spot          = $request->input('spot', '—');

            $levels     = $this->formattedLevels($candles, 'N/A');
            $support    = $levels['support'];
            $resistance = $levels['resistance'];

            $systemPrompt = "You are an expert BSE Sensex options trader. Return ONLY valid JSON — no markdown, no backticks. Base analysis strictly on provided candle data.";

            $userPrompt = <<<PROMPT
Analyze SENSEX option chart for trading decision.

Symbol: {$label} | Strike: {$strike} | Side: {$side}
Sensex Spot: {$spot} | Interval: {$interval}
Support: {$support} | Resistance: {$resistance}

{$candleSummary}

Return ONLY this JSON (real values, no generic text in reasons):
{
  "verdict": "bullish|bearish|sideways",
  "icon": "🟢|🔴|🟡",
  "title": "SHORT CAPS TITLE",
  "confidence": "confidence with %",
  "trendAlign": "description",
  "trendAlignColor": "#hex",
  "momentum": "description",
  "momentumColor": "#hex",
  "volSig": "volume signal",
  "volSigColor": "#hex",
  "risk": "🟢 LOW|🟡 MEDIUM|🔴 HIGH",
  "riskColor": "#hex",
  "reasons": ["5 specific candle-data-based observations"]
}
PROMPT;

            $data = $this->_callGroq($systemPrompt, $userPrompt);

            // Force correct S/R
            $data['keyLevels'] = ['support' => $support, 'resistance' => $resistance];

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('sensexAnalyze', $e, 'message');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/sensex-chat
    // ════════════════════════════════════════════════════════════════════════
    public function sensexChat(Request $request): JsonResponse
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('reply');
            }

            $message = $request->input('message', '');
            $history = $request->input('history', []);
            $context = $request->input('context', '');

            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho!']);
            }

            $systemPrompt = <<<PROMPT
Tum ek expert Sensex (BSE) options trader aur analyst ho.
Current Market Context: {$context}
Rules:
- Hinglish mein jawab do (Hindi + English mix)
- Short aur clear (max 4-5 lines)
- Specific numbers cite karo jo context mein hain
- HTML allowed: <b>, <br>
PROMPT;

            $messages = [];
            foreach (array_slice($history, -6) as $h) {
                if (in_array($h['role'] ?? '', ['user', 'assistant'])) {
                    $messages[] = ['role' => $h['role'], 'content' => $h['content']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            $reply = $this->_callGroqMulti($systemPrompt, $messages);
            return response()->json(['success' => true, 'reply' => $reply]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('sensexChat', $e, 'reply', false);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /angel/sensex-chart-chat
    // ════════════════════════════════════════════════════════════════════════
    public function sensexChartChat(Request $request): JsonResponse
    {
        try {
            if (empty($this->apiKey)) {
                return $this->missingApiKeyResponse('reply');
            }

            $message       = $request->input('message', '');
            $label         = $request->input('label', 'SENSEX Option');
            $strike        = $request->input('strike', '—');
            $side          = $request->input('side', '—');
            $context       = $request->input('context', []);
            $candles       = $context['candles'] ?? [];
            $candleSummary = $this->_buildCandleSummary($candles);

            if (empty($message)) {
                return response()->json(['reply' => 'Kuch poochho!']);
            }

            $levels       = $this->formattedLevels($candles);
            $currentPrice = $levels['current'];
            $support      = $levels['support'];
            $resistance   = $levels['resistance'];
            $spot         = $context['spot']     ?? '—';
            $interval     = $context['interval'] ?? '5m';

            $systemPrompt = "You are an expert BSE Sensex options trader. Reply in Hinglish (Hindi+English), 3-5 lines. HTML: <b>, <br>. Use real price numbers. Answer directly.";

            $userPrompt = <<<PROMPT
Symbol: {$label} | Strike: {$strike} | Side: {$side}
Spot: {$spot} | Current: {$currentPrice} | Interval: {$interval}
Support: {$support} | Resistance: {$resistance}
{$candleSummary}
Question: {$message}
PROMPT;

            $reply = $this->_callGroqText($systemPrompt, $userPrompt);
            return response()->json(['success' => true, 'reply' => $reply]);

        } catch (\Exception $e) {
            return $this->logAndErrorResponse('sensexChartChat', $e, 'reply', false);
        }
    }

    private function missingApiKeyResponse(string $field, int $status = 200): JsonResponse
    {
        return response()->json(['success' => false, $field => '❌ GROQ_API_KEY .env mein set nahi hai!'], $status);
    }

    private function formattedLevels(array $candles, string $fallback = '—'): array
    {
        $levels = $this->_calcSupportResistance($candles);

        return [
            'support' => $this->formatNumber($levels['support'], $fallback),
            'resistance' => $this->formatNumber($levels['resistance'], $fallback),
            'current' => $this->formatNumber($levels['current'], $fallback),
        ];
    }

    private function formatNumber(?float $value, string $fallback): string
    {
        return $value ? number_format($value, 2) : $fallback;
    }

    private function logAndErrorResponse(string $method, \Exception $e, string $field, bool $withServerErrorCode = true): JsonResponse
    {
        Log::error("AIAnalysis::{$method} — {$e->getMessage()}");

        return response()->json(
            ['success' => false, $field => $field === 'reply' ? '❌ Error: ' . $e->getMessage() : $e->getMessage()],
            $withServerErrorCode ? 500 : 200
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Calculate real support & resistance from candle OHLCV data.
     * Candle format: [timestamp, open, high, low, close, volume]
     * Uses swing high/low detection on last 50 candles.
     */
    private function _calcSupportResistance(array $candles): array
    {
        if (empty($candles)) {
            return ['support' => null, 'resistance' => null, 'current' => null];
        }

        // Normalize all candles first
        $recent = [];
        foreach (array_slice($candles, -50) as $c) {
            $n = $this->_normalizeCandle($c);
            if ($n && $n['cl'] > 0) $recent[] = $n;
        }

        if (empty($recent)) {
            return ['support' => null, 'resistance' => null, 'current' => null];
        }

        $last    = end($recent);
        $current = $last['cl'];
        $len     = count($recent);

        $swingHighs = [];
        $swingLows  = [];

        for ($i = 2; $i < $len - 2; $i++) {
            $h  = $recent[$i]['h'];
            $l  = $recent[$i]['l'];
            $h1 = $recent[$i-1]['h']; $h2 = $recent[$i-2]['h'];
            $h3 = $recent[$i+1]['h']; $h4 = $recent[$i+2]['h'];
            $l1 = $recent[$i-1]['l']; $l2 = $recent[$i-2]['l'];
            $l3 = $recent[$i+1]['l']; $l4 = $recent[$i+2]['l'];

            if ($h > 0 && $h > $h1 && $h > $h2 && $h > $h3 && $h > $h4) {
                $swingHighs[] = $h;
            }
            if ($l > 0 && $l < $l1 && $l < $l2 && $l < $l3 && $l < $l4) {
                $swingLows[] = $l;
            }
        }

        // Nearest resistance above current
        $resistance = null;
        if (!empty($swingHighs)) {
            $above      = array_filter($swingHighs, fn($v) => $v > $current);
            $resistance = !empty($above) ? min($above) : max($swingHighs);
        }
        if (!$resistance) {
            $allHighs   = array_filter(array_column($recent, 'h'), fn($v) => $v > 0);
            $resistance = !empty($allHighs) ? max($allHighs) : null;
        }

        // Nearest support below current
        $support = null;
        if (!empty($swingLows)) {
            $below   = array_filter($swingLows, fn($v) => $v < $current);
            $support = !empty($below) ? max($below) : min($swingLows);
        }
        if (!$support) {
            $allLows = array_filter(array_column($recent, 'l'), fn($v) => $v > 0);
            $support = !empty($allLows) ? min($allLows) : null;
        }

        return [
            'support'    => $support    ? round($support, 2)    : null,
            'resistance' => $resistance ? round($resistance, 2) : null,
            'current'    => $current    ? round($current, 2)    : null,
        ];
    }

    private function _callGroq(string $systemPrompt, string $userPrompt): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'])
            ->post($this->baseUrl, [
                'model'           => $this->model,
                'max_tokens'      => 1024,
                'temperature'     => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        $body = $response->json();
        Log::info('Groq Analyze', ['status' => $response->status()]);

        if (isset($body['error'])) throw new \Exception('Groq: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        if ($response->status() !== 200) throw new \Exception('Groq HTTP ' . $response->status());

        $text  = $body['choices'][0]['message']['content'] ?? '';
        $clean = trim(preg_replace('/```json\s*|```\s*/i', '', $text));
        $data  = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) throw new \Exception('JSON parse: ' . json_last_error_msg());
        return $data;
    }

    private function _callGroqText(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::timeout(20)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'])
            ->post($this->baseUrl, [
                'model'       => $this->model,
                'max_tokens'  => 512,
                'temperature' => 0.4,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        $body = $response->json();
        if (isset($body['error'])) throw new \Exception('Groq: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        if ($response->status() !== 200) throw new \Exception('Groq HTTP ' . $response->status());

        return $body['choices'][0]['message']['content'] ?? 'Response nahi mila.';
    }

    private function _callGroqMulti(string $systemPrompt, array $messages): string
    {
        $payload = array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages);

        $response = Http::timeout(20)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'])
            ->post($this->baseUrl, [
                'model'       => $this->model,
                'max_tokens'  => 512,
                'temperature' => 0.4,
                'messages'    => $payload,
            ]);

        $body = $response->json();
        if (isset($body['error'])) throw new \Exception('Groq: ' . ($body['error']['message'] ?? json_encode($body['error'])));
        if ($response->status() !== 200) throw new \Exception('Groq HTTP ' . $response->status());

        return $body['choices'][0]['message']['content'] ?? 'Response nahi mila.';
    }

    /**
     * Normalize a single candle — handles both:
     *   JS object: {time, open, high, low, close, volume}
     *   Raw array: [timestamp, open, high, low, close, volume]
     */
    private function _normalizeCandle(mixed $c): ?array
    {
        if (!is_array($c)) return null;

        // JS-style object keys (from blade/JS)
        if (isset($c['open'])) {
            return [
                'ts'  => $c['time']   ?? '—',
                'o'   => (float)($c['open']   ?? 0),
                'h'   => (float)($c['high']   ?? 0),
                'l'   => (float)($c['low']    ?? 0),
                'cl'  => (float)($c['close']  ?? 0),
                'vol' => $c['volume'] ?? '—',
            ];
        }

        // Raw numeric array [ts, o, h, l, cl, vol]
        if (isset($c[0])) {
            return [
                'ts'  => $c[0] ?? '—',
                'o'   => (float)($c[1] ?? 0),
                'h'   => (float)($c[2] ?? 0),
                'l'   => (float)($c[3] ?? 0),
                'cl'  => (float)($c[4] ?? 0),
                'vol' => $c[5]  ?? '—',
            ];
        }

        return null;
    }

    /**
     * Build candle summary for AI — last 15 candles with OHLCV + overall stats
     * Handles both JS objects {open,high,...} and raw arrays [ts,o,h,l,c,v]
     */
    private function _buildCandleSummary(array $candles): string
    {
        if (empty($candles)) return '(No candle data provided)';

        $last  = array_slice($candles, -15);
        $lines = ['=== RECENT CANDLES (last ' . count($last) . ', format: timestamp O H L C V) ==='];

        foreach ($last as $c) {
            $n = $this->_normalizeCandle($c);
            if (!$n || $n['o'] <= 0) continue;
            $dir     = $n['cl'] >= $n['o'] ? '🟢' : '🔴';
            $lines[] = "{$dir} {$n['ts']}  O:{$n['o']}  H:{$n['h']}  L:{$n['l']}  C:{$n['cl']}  V:{$n['vol']}";
        }

        // Overall trend summary
        $closes = [];
        foreach ($candles as $c) {
            $n = $this->_normalizeCandle($c);
            if ($n && $n['cl'] > 0) $closes[] = $n['cl'];
        }

        if (count($closes) >= 2) {
            $first   = $closes[0];
            $lastC   = end($closes);
            $change  = round((($lastC - $first) / $first) * 100, 2);
            $lines[] = "=== TOTAL PERIOD: Start {$first} → Current {$lastC} | Change: {$change}% ===";
        }

        return implode("\n", $lines);
    }
}
