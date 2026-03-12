// ── CSRF ──────────────────────────────────────────────────────────────────
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

// ── Chart loader hooks ────────────────────────────────────────────────────
window.showChartLoader = function(interval) {
    const msgs = {'3m':'Fetching 60 days...','5m':'Fetching 60 days...','15m':'Fetching 6 months...','1h':'Fetching 1 year...','1d':'Fetching 1 year...'};
    const isLong = ['15m','1h','1d'].includes(interval);

    const loader = document.getElementById('chart-overlay-loader');
    const loaderTxt = document.getElementById('overlay-loader-text');
    const loaderSub = document.getElementById('overlay-loader-sub');
    const topLoader = document.getElementById('chart-loading');
    const topTxt    = document.getElementById('chart-loading-text');

    if (loaderTxt) loaderTxt.textContent = msgs[interval] || 'Loading...';
    if (loaderSub) loaderSub.style.display = isLong ? 'block' : 'none';
    if (loader) loader.style.display = 'flex';
    if (topLoader) topLoader.style.display = 'flex';
    if (topTxt) topTxt.textContent = msgs[interval] || 'Loading...';

    // Update interval label
    const lbl = document.getElementById('current-interval-label');
    if (lbl) lbl.textContent = interval.toUpperCase();
};

window.hideChartLoader = function(candleCount) {
    const loader    = document.getElementById('chart-overlay-loader');
    const topLoader = document.getElementById('chart-loading');
    const info      = document.getElementById('candle-info');

    if (loader)    loader.style.display    = 'none';
    if (topLoader) topLoader.style.display = 'none';

    if (info && candleCount) {
        info.textContent   = candleCount + ' candles';
        info.style.display = 'inline';
    }

    // Auto-trigger AI after chart loads
    setTimeout(() => window.triggerAIAnalysis(), 1000);
};

// ── Interval button active state ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nt-interval-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.nt-interval-btn').forEach(b => {
                b.style.background  = '#091020';
                b.style.color       = '#4a6888';
                b.style.borderColor = '#1a2740';
            });
            btn.style.background  = '#1565a0';
            btn.style.color       = '#c8e0f4';
            btn.style.borderColor = '#1565a0';
        });
    });

    // Chat scroll observer
    const chatBox = document.getElementById('chat-messages');
    if (chatBox) {
        new MutationObserver(() => { chatBox.scrollTop = chatBox.scrollHeight; })
            .observe(chatBox, { childList: true, subtree: true });
    }
});

// ── AI Analysis ───────────────────────────────────────────────────────────
window.triggerAIAnalysis = async function() {
    // Hide all states
    document.getElementById('ai-waiting').style.display       = 'none';
    document.getElementById('ai-error').style.display         = 'none';
    document.getElementById('ai-result-content').style.display = 'none';
    document.getElementById('ai-loading').style.display        = 'flex';

    try {
        const interval = window._currentInterval || '5m';
        const candles  = (window._lastCandles || []).slice(-30);

        if (!candles.length) {
            throw new Error('Chart data abhi load nahi hua. Thoda wait karo.');
        }

        const res = await fetch('/angel/nifty-ai-analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': _csrf,
            },
            body: JSON.stringify({ interval, candles }),
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const json = await res.json();

        document.getElementById('ai-loading').style.display = 'none';

        if (!json.success || !json.data) {
            throw new Error(json.message || 'Response data nahi mila');
        }

        renderAIResult(json.data);

    } catch(err) {
        document.getElementById('ai-loading').style.display   = 'none';
        document.getElementById('ai-error').style.display     = 'flex';
        document.getElementById('ai-error-msg').textContent   = '❌ ' + err.message;
        console.error('AI Analysis error:', err);
    }
};

function renderAIResult(d) {
    // Verdict
    document.getElementById('ai-icon').textContent       = d.icon       || '📊';
    document.getElementById('ai-title').textContent      = d.title      || '--';
    document.getElementById('ai-confidence').textContent = d.confidence || '--';

    // Verdict box color
    const box = document.getElementById('ai-verdict-box');
    const colors = {
        bullish: { bg:'#f0fdf4', border:'#86efac' },
        bearish: { bg:'#fef2f2', border:'#fca5a5' },
        neutral: { bg:'#fffbeb', border:'#fde68a' },
    };
    const c = colors[d.verdict] || colors.neutral;
    box.style.background  = c.bg;
    box.style.borderColor = c.border;

    // Metrics
    const setVal = (id, val, color) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val || '--';
        if (color) el.style.color = color;
    };
    setVal('ai-trend',    d.trendAlign,  d.trendAlignColor);
    setVal('ai-momentum', d.momentum,    d.momentumColor);
    setVal('ai-vol-sig',  d.volSig,      d.volSigColor);
    setVal('ai-risk',     d.risk,        d.riskColor);

    // Support / Resistance
    if (d.keyLevels?.support) {
        setVal('ai-support', d.keyLevels.support, '#16a34a');
        document.getElementById('ai-support-row').style.display = '';
    } else {
        document.getElementById('ai-support-row').style.display = 'none';
    }
    if (d.keyLevels?.resistance) {
        setVal('ai-resist', d.keyLevels.resistance, '#dc2626');
        document.getElementById('ai-resist-row').style.display = '';
    } else {
        document.getElementById('ai-resist-row').style.display = 'none';
    }

    // Updated time
    const now = new Date().toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('ai-updated').textContent = 'Updated: ' + now;

    // Show result
    document.getElementById('ai-result-content').style.display = 'flex';
}

// Auto-refresh every 5 min if result is showing
setInterval(() => {
    if (document.getElementById('ai-result-content').style.display !== 'none') {
        triggerAIAnalysis();
    }
}, 5 * 60 * 1000);
