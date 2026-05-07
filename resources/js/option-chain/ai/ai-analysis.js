'use strict';

import { _csrf, _el, _esc } from '../utils';
import { renderAI } from './ai-render';

export async function runAIAnalysis() {

    if (window._aiAnalyzing) return;

    window._aiAnalyzing = true;

    const btn    = _el('aiAnalyzeBtn');
    const btnTxt = _el('aiAnalyzeBtnTxt');

    if (btn) {
        btn.style.opacity = '.5';
    }

    if (btnTxt) {
        btnTxt.textContent = '⏳ Analyzing...';
    }

    try {

        const candles = (window._lastCandles || []).slice(-30);

        const interval =
            document.querySelector('[data-iv].bg-indigo-600')
                ?.dataset?.iv || 'FIVE_MINUTE';

        const res = await fetch('/angel/nifty-ai-analyze', {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': _csrf(),
            },

            body: JSON.stringify({
                strike: window._modalStrike,
                side: window._modalSide,
                label: window._modalLabel,
                source: 'Chart',
                isAuto: false,
                candles,
                interval,
            }),
        });

        const json = await res.json();

        if (!json.success || !json.data) {

        let msg = 'AI service temporarily unavailable';

        if (json.message) {

            const m = json.message.toLowerCase();

            if (m.includes('rate limit')) {

                msg = 'AI server busy — retry shortly';

            } else if (m.includes('quota')) {

                msg = 'Daily AI quota exhausted';

            } else if (m.includes('timeout')) {

                msg = 'AI request timeout';

            } else if (m.includes('404')) {

                msg = 'AI endpoint not found';

            } else if (m.includes('500')) {

                msg = 'Internal AI server error';
            }
        }

        throw new Error(msg);
        }

        renderAI(json.data);

        } catch (err) {

        const waiting = _el('aiWaiting');

        if (waiting) {
            waiting.innerHTML =
                `<span style="color:#dc2626;">❌ ${_esc(err.message)}</span>`;
        }

    } finally {

        window._aiAnalyzing = false;

        if (btn) {
            btn.style.opacity = '1';
        }

        if (btnTxt) {
            btnTxt.textContent = '🔄 Re-Analyze';
        }
    }
}