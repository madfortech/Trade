<?php

namespace App\Services\AIAnalysis;

class PromptFactory
{
    public function niftyAnalyze(string $interval, string $currentPrice, string $support, string $resistance, string $summary): array
    {
        $systemPrompt = <<<SYS
        You are an expert NIFTY 50 index trader and technical analyst.
        You MUST return ONLY a valid JSON object — no markdown, no backticks, no explanation, no text outside JSON.
        You MUST write ALL text fields in ENGLISH ONLY. No Hindi, no Hinglish.
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
        6. ALL text values MUST be in English only.

        Return ONLY this JSON:
        {
        "verdict": "",
        "icon": "🟢 or 🔴 or 🟡",
        "title": "ONLY ONE OF: STRONG UPTREND, STRONG DOWNTREND, SIDEWAYS MARKET, MARKET REVERSAL, BREAKOUT LIKELY",
        "confidence": "ONLY FORMAT: 85% Confidence",
        "trendAlign": "e.g. ✅ Bullish Trend or ⚠ Mixed Signals",
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
        "Candle-based reason 1 in English",
        "Candle-based reason 2 in English",
        "Candle-based reason 3 in English",
        "Candle-based reason 4 in English",
        "Candle-based reason 5 in English"
        ]
        }
        PROMPT;

        return [$systemPrompt, $userPrompt];
    }

    public function niftyChat(string $interval, string $currentPrice, string $support, string $resistance, string $summary, string $message): array
    {
        $systemPrompt = <<<SYS
        You are an expert NIFTY 50 trader and technical analyst.
        Reply in ENGLISH ONLY. Maximum 4-5 lines. Be direct and specific.
        HTML allowed: <b>, <br>, <span style='color:#...'>.
        Use the REAL price numbers from the data provided. No generic advice.
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
        $systemPrompt = 'You are an expert NSE options trader and technical analyst. Return ONLY valid JSON — no markdown, no backticks. ALL text fields must be in ENGLISH ONLY. Base analysis strictly on provided candle data.';

        $userPrompt = <<<PROMPT
        Analyze this option chart for a real trading decision.

        Symbol: {$label} | Strike: {$strike} | Side: {$side} | Trigger: {$source}
        Current Price: {$currentPrice} | Support: {$support} | Resistance: {$resistance}

        {$summary}

        Rules:
        - ALL text values must be in English only
        - reasons must be 5 specific observations from the candle data (no generic text)
        - Use real price numbers from data

        Return ONLY this JSON:
        {
        "verdict": "bullish|bearish|sideways",
        "icon": "🟢|🔴|🟡",
        "title": "SHORT CAPS TITLE IN ENGLISH",
        "confidence": "confidence with % e.g. High Confidence (78%)",
        "trendAlign": "trend description in English",
        "trendAlignColor": "#4ade80 or #f87171 or #facc15",
        "momentum": "momentum description in English",
        "momentumColor": "#4ade80 or #f87171 or #facc15",
        "volSig": "volume signal in English",
        "volSigColor": "#4ade80 or #f87171 or #facc15",
        "risk": "🟢 LOW|🟡 MEDIUM|🔴 HIGH",
        "riskColor": "#4ade80 or #facc15 or #f87171",
        "reasons": [
        "Specific candle-based observation 1",
        "Specific candle-based observation 2",
        "Specific candle-based observation 3",
        "Specific candle-based observation 4",
        "Specific candle-based observation 5"
        ]
        }
        PROMPT;

        return [$systemPrompt, $userPrompt];
    }

    public function optionChartChat(string $label, ?string $strike, ?string $side, string $currentPrice, string $support, string $resistance, string $summary, string $message): array
    {
        $systemPrompt = 'You are an expert NSE options trader and technical analyst. Reply in ENGLISH ONLY. Maximum 4-5 lines. HTML allowed: <b>, <br>. Use real price numbers from the data. Answer directly and specifically.';

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
        $systemPrompt = 'You are an expert BSE Sensex options trader and technical analyst. Return ONLY valid JSON — no markdown, no backticks. ALL text fields must be in ENGLISH ONLY. Base analysis strictly on provided candle data.';

        $userPrompt = <<<PROMPT
        Analyze this SENSEX option chart for a real trading decision.

        Symbol: {$label} | Strike: {$strike} | Side: {$side}
        Sensex Spot: {$spot} | Interval: {$interval}
        Support: {$support} | Resistance: {$resistance}

        {$summary}

        Rules:
        - ALL text values must be in English only
        - reasons must be 5 specific observations from the candle data (no generic text)

        Return ONLY this JSON:
        {
        "verdict": "bullish|bearish|sideways",
        "icon": "🟢|🔴|🟡",
        "title": "SHORT CAPS TITLE IN ENGLISH",
        "confidence": "confidence with %",
        "trendAlign": "trend description in English",
        "trendAlignColor": "#4ade80 or #f87171 or #facc15",
        "momentum": "momentum description in English",
        "momentumColor": "#4ade80 or #f87171 or #facc15",
        "volSig": "volume signal in English",
        "volSigColor": "#4ade80 or #f87171 or #facc15",
        "risk": "🟢 LOW|🟡 MEDIUM|🔴 HIGH",
        "riskColor": "#4ade80 or #facc15 or #f87171",
        "reasons": [
        "Specific candle-based observation 1",
        "Specific candle-based observation 2",
        "Specific candle-based observation 3",
        "Specific candle-based observation 4",
        "Specific candle-based observation 5"
        ]
        }
        PROMPT;

        return [$systemPrompt, $userPrompt];
    }

    public function sensexChatSystem(string $context): string
    {
        return <<<PROMPT
        You are an expert Sensex (BSE) options trader and technical analyst.
        Current Market Context: {$context}
        Rules:
        - Reply in ENGLISH ONLY
        - Short and clear (max 4-5 lines)
        - Cite specific numbers from the context
        - HTML allowed: <b>, <br>
        PROMPT;
    }

    public function sensexChartChat(string $label, string $strike, string $side, string $spot, string $currentPrice, string $interval, string $support, string $resistance, string $summary, string $message): array
    {
        $systemPrompt = 'You are an expert BSE Sensex options trader and technical analyst. Reply in ENGLISH ONLY. Maximum 4-5 lines. HTML allowed: <b>, <br>. Use real price numbers from the data. Answer directly.';

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
