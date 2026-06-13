'use strict';

import '../option-chain-chart';

import { installChartHooks } from './chart/chart-hooks';

import { runAIAnalysis } from './ai/ai-analysis';

import { sendChat } from './chat/chat-api';

// ─────────────────────────────────────────────
// GLOBAL FUNCTIONS
// ─────────────────────────────────────────────

window.runAIAnalysis =
    runAIAnalysis;

window.sendChat =
    sendChat;

// ✅ EXPIRY CHANGE
window.changeNiftyExpiry =
function(expiry) {

    const url =
        new URL(
            window.location.href
        );

    url.searchParams.set(
        'expiry',
        expiry
    );

    window.location.href =
        url.toString();
};

// ✅ AUTO REFRESH
window.toggleAutoRefresh =
function() {

    const btn =
        document.getElementById(
            'autoRefreshBtn'
        );

    if (!btn) return;

    const on =
        btn.dataset.on === '1';

    if (on) {

        btn.dataset.on = '0';

        btn.textContent = 'OFF';

        btn.classList.remove(
            'bg-green-600',
            'text-white'
        );

        btn.classList.add(
            'bg-gray-200',
            'text-gray-600'
        );

    } else {

        btn.dataset.on = '1';

        btn.textContent = 'ON';

        btn.classList.remove(
            'bg-gray-200',
            'text-gray-600'
        );

        btn.classList.add(
            'bg-green-600',
            'text-white'
        );
    }
};

// ─────────────────────────────────────────────
// CHART RANGE
// ─────────────────────────────────────────────

window.changeChartRange =
function(range) {

   if (
        !window._chart ||
        typeof window._chart.timeScale !== 'function'
    ) {
        return;
    }

    // REMOVE ACTIVE
    document
        .querySelectorAll(
            '.chart-zoom-btn'
        )
        .forEach(btn => {

            btn.classList.remove(
                'bg-indigo-600',
                'text-white'
            );

            btn.classList.add(
                'text-slate-500'
            );
        });

    // ACTIVE BUTTON
    const activeBtn =
        document.querySelector(
            `.chart-zoom-btn[data-range="${range}"]`
        );

    if (activeBtn) {

        activeBtn.classList.add(
            'bg-indigo-600',
            'text-white'
        );

        activeBtn.classList.remove(
            'text-slate-500'
        );
    }

    const timeScale =
        window._chart.timeScale();

    switch (range) {

        case '1D':

            timeScale.applyOptions({
                barSpacing: 12
            });

            break;

        case '5D':

            timeScale.applyOptions({
                barSpacing: 10
            });

            break;

        case '1M':

            timeScale.applyOptions({
                barSpacing: 8
            });

            break;

        case '3M':

            timeScale.applyOptions({
                barSpacing: 6
            });

            break;

        case '6M':

            timeScale.applyOptions({
                barSpacing: 4
            });

            break;

        case '1Y':

            timeScale.applyOptions({
                barSpacing: 2
            });

            break;

        case '5Y':

            timeScale.applyOptions({
                barSpacing: 1
            });

            break;
    }
};


// ─────────────────────────────────────────────

document.addEventListener(
    'DOMContentLoaded',
    () => {

        installChartHooks();
    }
);