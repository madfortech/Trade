<?php

namespace App\Services\AIAnalysis\Prompts;

class SensexPrompt
{
    public function analyze(
        string $label,
        string $strike,
        string $side,
        string $spot,
        string $interval,
        string $support,
        string $resistance,
        string $summary
    ): array {

    $systemPrompt = <<<SYS
        You are an expert SENSEX trader.
        Return ONLY valid JSON.
        SYS;

    $userPrompt = <<<PROMPT
        Analyze this SENSEX option chart.

        Spot: {$spot}
        Interval: {$interval}
        Support: {$support}
        Resistance: {$resistance}

        {$summary}

        Return ONLY JSON:

        {
        "verdict":"bullish|bearish|sideways",
        "icon":"🟢|🔴|🟡",
        "title":"ONLY ONE OF: STRONG UPTREND, STRONG DOWNTREND, SIDEWAYS MARKET",
        "confidence":"ONLY FORMAT: 85% Confidence"
        }
        PROMPT;

    return [$systemPrompt, $userPrompt];
    }
}