'use strict';

const setText = (id, value) => {

    const el = document.getElementById(id);

    if (el) {
        el.textContent = value ?? '--';
    }
};

export function renderAI(data = {}) {

    setText(
        'ai-title',
        data.title ||
        data.verdict ||
        data.signal ||
        '--'
    );

    setText(
        'ai-confidence',
        data.confidence ||
        '--'
    );

    setText(
        'ai-trend',
        data.trend ||
        '--'
    );

    setText(
        'ai-momentum',
        data.momentum ||
        '--'
    );

    setText(
        'ai-vol-sig',
        data.volume ||
        data.volume_signal ||
        '--'
    );

    setText(
        'ai-risk',
        data.risk ||
        '--'
    );

    setText(
        'ai-support',
        data.support ||
        '--'
    );

    setText(
        'ai-resist',
        data.resistance ||
        '--'
    );

    const reasons =
        document.getElementById('aiReasons');

    if (reasons) {

        const items =
            data.reasons || [];

        reasons.innerHTML =
            Array.isArray(items)
                ? items.map(r => `
                    <div class="flex gap-2">
                        <span>•</span>
                        <span>${r}</span>
                    </div>
                `).join('')
                : '';
    }

    setText(
        'ai-updated',
        'Updated: ' +
        new Date().toLocaleTimeString()
    );
}