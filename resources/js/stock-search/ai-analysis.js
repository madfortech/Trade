'use strict';

const _el = id => document.getElementById(id);

const _csrf = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

const _esc = str =>
    String(str ?? '');

import {
    renderAI
} from './ai-render';

window.runAIAnalysis = async function () {

    if (window._aiAnalyzing) {
        return;
    }

    window._aiAnalyzing = true;

    const btn = _el('aiAnalyzeBtn');
    const waiting = _el('aiWaiting');
    const skeleton = _el('aiSkeleton');
    const verdict = _el('aiVerdictArea');

    if (waiting) {
        waiting.style.display = 'none';
    }

    if (verdict) {
        verdict.style.display = 'none';
    }

    if (skeleton) {
        skeleton.classList.remove('hidden');
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Analyzing...';
    }

    try {

        const candles =
            (window._stockCandles || []).slice(-50);

        if (!candles.length) {
            throw new Error('No candle data loaded');
        }

        const stock =
            window.currentStock || {};

        const res = await fetch(
            '/angel/stock-ai-analyze',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': _csrf(),
                },

                body: JSON.stringify({

                    symbol:
                        stock.symbol || '',

                    token:
                        stock.token || '',

                    exchange:
                        stock.exchange || '',

                    candles,
                }),
            }
        );

        const json = await res.json();

        if (!json.success || !json.data) {

            throw new Error(
                json.message || 'AI analysis failed'
            );
        }

        if (skeleton) {
            skeleton.classList.add('hidden');
        }

        if (verdict) {
            verdict.style.display = 'block';
        }

        renderAI(json.data);

    } catch (err) {

        console.error(err);

        if (skeleton) {
            skeleton.classList.add('hidden');
        }

        if (waiting) {

            waiting.style.display = 'flex';

            waiting.innerHTML = `
                <div class="text-red-500 text-xs">
                    ❌ ${_esc(err.message)}
                </div>
            `;
        }

    } finally {

        window._aiAnalyzing = false;

        if (btn) {

            btn.disabled = false;

            btn.innerHTML = '⚡ Analyze';
        }
    }
};