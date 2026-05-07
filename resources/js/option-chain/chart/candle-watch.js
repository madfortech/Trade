'use strict';

import { runAIAnalysis } from '../ai/ai-analysis';

let _candleWatchTimer = null;

export function waitForCandlesAndAnalyze() {

    if (_candleWatchTimer) {
        clearInterval(_candleWatchTimer);
    }

    let n = 0;

    _candleWatchTimer = setInterval(() => {

        n++;

        const candles = window._lastCandles || [];

        if (candles.length > 5) {

            clearInterval(_candleWatchTimer);

            setTimeout(runAIAnalysis, 300);
        }

        if (n > 40) {
            clearInterval(_candleWatchTimer);
        }

    }, 500);
}