<?php

namespace App\Services\AIAnalysis\Prompts;

class OptionPrompt
{
    public function analyze(
        string $label,
        ?string $strike,
        ?string $side,
        string $source,
        string $currentPrice,
        string $support,
        string $resistance,
        string $summary
    ): array {

    $systemPrompt =
            'You are an expert NSE options trader. Return ONLY valid JSON.';

    $userPrompt = <<<PROMPT
        Analyze this option chart.

        Symbol: {$label}
        Strike: {$strike}
        Side: {$side}

        Current Price: {$currentPrice}
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