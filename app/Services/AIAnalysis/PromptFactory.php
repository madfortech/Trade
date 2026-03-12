<?php

namespace App\Services\AIAnalysis;

class PromptFactory
{
    public function niftyAnalyze(string $interval, string $currentPrice, string $support, string $resistance, string $summary): array
    {
        $systemPrompt = <<<SYS
You are an expert NIFTY 50 index trader and technical analyst.
You MUST return ONLY a valid JSON object — no markdown, no backticks, no explanation, no text outside JSON.
Base your entire analysis STRICTLY on the candle data (OHLCV) provided. Do NOT invent or use placeholder values.
SYS;

        $userPrompt = <<<PROMPT
Analyze this NIFTY 50 candlestick data and provide a real, data-driven trading analysis.

Interval: {$interval}
Current Price: {$currentPrice}
Calculated Support Level: {$support}
Calculated Resistance Level: {$resistance}

{$summary}

Rules:
1. verdict = "bullish", "bearish", or "sideways" based on actual price action
2. keyLevels.support MUST equal exactly: {$support}
3. keyLevels.resistance MUST equal exactly: {$resistance}
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
    "support": "{$support}",
    "resistance": "{$resistance}"
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

        return [$systemPrompt, $userPrompt];
    }

    public function niftyChat(string $interval, string $currentPrice, string $support, string $resistance, string $summary, string $message): array
    {
        $systemPrompt = <<<SYS
You are an expert NIFTY 50 trader. Reply in Hinglish (Hindi+English mix), 3-5 lines max.
HTML allowed: <b>, <br>, <span style='color:#...'>.
Be direct and specific — use the REAL price numbers from the data provided. No generic advice.
SYS;

        $userPrompt = <<<PROMPT
Interval: {$interval} | Current Price: {$currentPrice}
Support: {$support} | Resistance: {$resistance}
{$summary}
Question: {$message}
PROMPT;

        return [$systemPrompt, $userPrompt];
    }

    public function optionAnalyze(string $label, ?string $strike, ?string $side, string $source, string $currentPrice, string $support, string $resistance, string $summary): array
    {
        $systemPrompt = 'You are an expert NSE options trader. Return ONLY valid JSON — no markdown, no backticks. Base analysis strictly on provided candle data.';

        $userPrompt = <<<PROMPT
Analyze this option chart for a real trading decision.

Symbol: {$label} | Strike: {$strike} | Side: {$side} | Trigger: {$source}
Current Price: {$currentPrice} | Support: {$support} | Resistance: {$resistance}

{$summary}

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

        return [$systemPrompt, $userPrompt];
    }

    public function optionChartChat(string $label, ?string $strike, ?string $side, string $currentPrice, string $support, string $resistance, string $summary, string $message): array
    {
        $systemPrompt = 'You are an expert NSE options trader. Reply in Hinglish (Hindi+English), 3-5 lines. HTML: <b>, <br>. Use real price numbers from data. Answer directly.';

        $userPrompt = <<<PROMPT
Symbol: {$label} | Strike: {$strike} | Side: {$side}
Current: {$currentPrice} | Support: {$support} | Resistance: {$resistance}
{$summary}
Question: {$message}
PROMPT;

        return [$systemPrompt, $userPrompt];
    }

    public function sensexAnalyze(string $label, ?string $strike, ?string $side, string $spot, string $interval, string $support, string $resistance, string $summary): array
    {
        $systemPrompt = 'You are an expert BSE Sensex options trader. Return ONLY valid JSON — no markdown, no backticks. Base analysis strictly on provided candle data.';

        $userPrompt = <<<PROMPT
Analyze SENSEX option chart for trading decision.

Symbol: {$label} | Strike: {$strike} | Side: {$side}
Sensex Spot: {$spot} | Interval: {$interval}
Support: {$support} | Resistance: {$resistance}

{$summary}

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

        return [$systemPrompt, $userPrompt];
    }

    public function sensexChatSystem(string $context): string
    {
        return <<<PROMPT
Tum ek expert Sensex (BSE) options trader aur analyst ho.
Current Market Context: {$context}
Rules:
- Hinglish mein jawab do (Hindi + English mix)
- Short aur clear (max 4-5 lines)
- Specific numbers cite karo jo context mein hain
- HTML allowed: <b>, <br>
PROMPT;
    }

    public function sensexChartChat(string $label, string $strike, string $side, string $spot, string $currentPrice, string $interval, string $support, string $resistance, string $summary, string $message): array
    {
        $systemPrompt = 'You are an expert BSE Sensex options trader. Reply in Hinglish (Hindi+English), 3-5 lines. HTML: <b>, <br>. Use real price numbers. Answer directly.';

        $userPrompt = <<<PROMPT
Symbol: {$label} | Strike: {$strike} | Side: {$side}
Spot: {$spot} | Current: {$currentPrice} | Interval: {$interval}
Support: {$support} | Resistance: {$resistance}
{$summary}
Question: {$message}
PROMPT;

        return [$systemPrompt, $userPrompt];
    }
}
