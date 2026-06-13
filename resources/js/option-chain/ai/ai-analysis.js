'use strict';

import {
    _csrf,
    _el,
    _esc
} from '../utils';

import {
    renderAI
} from './ai-render';

export async function runAIAnalysis() {
    
    if (window._aiAnalyzing) {
        return;
    }

    window._aiAnalyzing = true;

    const btn =
        _el('aiAnalyzeBtn');

    const waiting =
        _el('aiWaiting');

    const skeleton =
        _el('aiSkeleton');

    const verdict =
        _el('aiVerdictArea');

    // RESET UI
    if (waiting) {
        waiting.style.display = 'none';
    }

    if (verdict) {
        verdict.style.display = 'none';
    }

    // SHOW LOADER
    if (skeleton) {

        skeleton.classList.remove(
            'hidden'
        );

        skeleton.style.display =
            'flex';
    }

    // BUTTON
    if (btn) {

        btn.disabled = true;

        btn.style.opacity = '.5';

        btn.innerHTML =
            '⏳ Analyzing...';
    }

    try {

        const candles =
            (
                window._lastCandles || []
            ).slice(-30);

        if (!candles.length) {

            throw new Error(
                'Candles not loaded yet'
            );
        }

        const interval =
            document
                .querySelector(
                    '[data-iv].bg-indigo-600'
                )
                ?.dataset?.iv
            || 'FIVE_MINUTE';

        // GROQ BACKEND ONLY
        const res =
            await fetch(
                '/angel/nifty-ai-analyze',
                {
                    method: 'POST',

                    headers: {

                        'Content-Type':
                            'application/json',

                        'X-CSRF-TOKEN':
                            _csrf(),
                    },

                    body: JSON.stringify({

                        strike:
                            window._modalStrike,

                        side:
                            window._modalSide,

                        label:
                            window._modalLabel,

                        source:
                            'Chart',

                        isAuto:
                            false,

                        candles,

                        interval,
                    }),
                }
            );

        const json =
            await res.json();

        if (
            !json.success ||
            !json.data
        ) {

            throw new Error(
                json.message ||
                'AI failed'
            );
        }

        // HIDE LOADER
        if (skeleton) {

            skeleton.classList.add(
                'hidden'
            );

            skeleton.style.display =
                'none';
        }

        // SHOW RESULT
        if (verdict) {

            verdict.style.display =
                'block';
        }

        renderAI(
            json.data
        );

    } catch (err) {

        console.error(
            'AI ANALYSIS ERROR:',
            err
        );

        // HIDE LOADER
        if (skeleton) {

            skeleton.classList.add(
                'hidden'
            );

            skeleton.style.display =
                'none';
        }

        // SHOW ERROR
        if (waiting) {

            waiting.style.display =
                'flex';

            waiting.innerHTML = `
                <div
                    style="
                        color:#dc2626;
                        font-size:12px;
                        text-align:center;
                        line-height:1.6;
                    "
                >

                    ❌ ${_esc(err.message)}

                </div>
            `;
        }

    } finally {

        window._aiAnalyzing = false;

        if (btn) {

            btn.disabled = false;

            btn.style.opacity = '1';

            btn.innerHTML =
                '⚡ Analyze';
        }
    }
}