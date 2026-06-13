'use strict';

import { resetAIPanel }
from '../ai/ai-render';

export function installChartHooks() {

    const origOpen =
        window.openAngelChart;

    window.openAngelChart = function(

        token,
        label,
        exchange,
        peToken,
        strike,
        side,
        expiry

    ) {

        resetAIPanel();

        if (
            typeof origOpen ===
            'function'
        ) {

            origOpen(

                token,
                label,
                exchange,
                peToken,
                strike,
                side,
                expiry
            );
        }

        // ❌ AUTO ANALYSIS REMOVED
    };
}