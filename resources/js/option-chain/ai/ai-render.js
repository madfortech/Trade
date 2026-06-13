'use strict';

import { _el } from '../utils';

export function resetAIPanel() {

    window._aiAnalyzing = false;

    const waiting  = _el('aiWaiting');
    const skeleton = _el('aiSkeleton');
    const verdict  = _el('aiVerdictArea');

    // SHOW WAITING
    if (waiting) {

        waiting.classList.remove('hidden');

        waiting.style.display = 'flex';
    }

    // HIDE LOADING
    if (skeleton) {

        skeleton.classList.add('hidden');

        skeleton.style.display = 'none';
    }

    // HIDE RESULT
    if (verdict) {

        verdict.classList.add('hidden');

        verdict.style.display = 'none';
    }

    const wp = waiting?.querySelector('p');

    if (wp) {

        wp.innerHTML = `
            Open a strike chart and click<br>

            <strong style="color:#4f46e5;">
                “Analyze”
            </strong>

            to generate AI insights.
        `;
    }

    // RESET BUTTON
    const btn = _el('aiAnalyzeBtn');

    const btnTxt = _el('aiAnalyzeBtnTxt');

    if (btn) {

        btn.style.opacity = '1';

        btn.style.pointerEvents = 'auto';
    }

    if (btnTxt) {

        btnTxt.textContent = '⚡ Analyze';
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

    const skeleton = _el('aiSkeleton');

    const waiting  = _el('aiWaiting');

    const verdict  = _el('aiVerdictArea');

    // HIDE LOADING
    if (skeleton) {

        skeleton.classList.add('hidden');

        skeleton.style.display = 'none';
    }

    // HIDE WAITING
    if (waiting) {

        waiting.classList.add('hidden');

        waiting.style.display = 'none';
    }

    // SHOW RESULT
    if (verdict) {

        verdict.classList.remove('hidden');

        verdict.style.display = 'block';
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

    // API FIELD FIXES
    set('ai-trend', d.trendAlign);

    set('ai-momentum', d.momentum);

    set('ai-vol-sig', d.volSig);

    set('ai-risk', d.risk);

    // SUPPORT/RESISTANCE
    set(
        'ai-support',
        d.keyLevels?.support
    );

    set(
        'ai-resist',
        d.keyLevels?.resistance
    );

    // UPDATED TIME
    set(
        'ai-updated',
        new Date().toLocaleTimeString()
    );

    // COLOR BOX
    const box = _el('ai-verdict-box');

    if (box) {

        box.style.border = '1px solid #e2e8f0';

        box.style.background = '#fff';

        const verdictText =
            String(d.verdict || '')
                .toLowerCase();

        if (verdictText.includes('bull')) {

            box.style.border =
                '1px solid #86efac';

            box.style.background =
                '#f0fdf4';

        } else if (
            verdictText.includes('bear')
        ) {

            box.style.border =
                '1px solid #fca5a5';

            box.style.background =
                '#fef2f2';

        } else {

            box.style.border =
                '1px solid #cbd5e1';

            box.style.background =
                '#f8fafc';
        }
    }

    // REASONS
    const reasonsWrap =
        _el('aiReasons');

    if (
        reasonsWrap &&
        Array.isArray(d.reasons)
    ) {

        reasonsWrap.innerHTML =
            d.reasons.map(r => `

                <div class="text-xs text-slate-700 border-b py-1">

                    • ${r}

                </div>

            `).join('');
    }
}