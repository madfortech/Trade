'use strict';

import { _el } from '../utils';

export function resetAIPanel() {

    window._aiAnalyzing = false;

    const waiting  = _el('ai-waiting');
    const skeleton = _el('ai-loading');
    const verdict  = _el('ai-result-content');

    // SHOW WAITING
    if (waiting) {
        waiting.style.display = 'flex';
    }

    // HIDE LOADING
    if (skeleton) {
        skeleton.style.display = 'none';
    }

    // HIDE RESULT
    if (verdict) {
        verdict.style.display = 'none';
    }

    const wp = waiting?.querySelector('p');

    if (wp) {

        wp.innerHTML = `
            Click 'Analyze Now' for<br>
            latest Nifty insights.
        `;
    }

    // RESET BUTTON
    const btn = _el('ai-refresh-btn');

    if (btn) {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    }

    // RESET VALUES
    const resetIds = [
        'ai-title',
        'ai-confidence',
        'ai-trend',
        'ai-momentum',
        'ai-vol-sig',
        'ai-risk',
        'ai-support',
        'ai-resist',
        'ai-updated'
    ];

    resetIds.forEach(id => {

        const el = _el(id);

        if (el) {
            el.textContent = '--';
        }
    });

    const icon = _el('ai-icon');

    if (icon) {
        icon.textContent = '📊';
    }
}

export function renderAI(d) {

    const skeleton = _el('ai-loading');
    const waiting  = _el('ai-waiting');
    const verdict  = _el('ai-result-content');

    // HIDE LOADING
    if (skeleton) {
        skeleton.style.display = 'none';
    }

    // HIDE WAITING
    if (waiting) {
        waiting.style.display = 'none';
    }

    // SHOW RESULT
    if (verdict) {
        verdict.style.display = 'flex';
    }

    const set = (id, val) => {

        const e = _el(id);

        if (e) {
            e.textContent = val || '--';
        }
    };

    // MAIN VERDICT
    set('ai-icon', d.icon);
    set('ai-title', d.title);
    set('ai-confidence', d.confidence);

    // METRICS
    set('ai-trend', d.trend);
    set('ai-momentum', d.momentum);
    set('ai-vol-sig', d.volumeSignal);
    set('ai-risk', d.risk);

    set('ai-support', d.support);
    set('ai-resist', d.resistance);

    // UPDATED TIME
    set('ai-updated', d.updatedAt);

    // COLOR BOX BASED ON TREND
    const box = _el('ai-verdict-box');

    if (box) {

        box.style.border = '1px solid #e2e8f0';
        box.style.background = '#fff';

        const title = String(d.title || '').toUpperCase();

        if (title.includes('UPTREND') || title.includes('BULL')) {

            box.style.border = '1px solid #86efac';
            box.style.background = '#f0fdf4';

        } else if (title.includes('DOWNTREND') || title.includes('BEAR')) {

            box.style.border = '1px solid #fca5a5';
            box.style.background = '#fef2f2';

        } else {

            box.style.border = '1px solid #cbd5e1';
            box.style.background = '#f8fafc';
        }
    }
}