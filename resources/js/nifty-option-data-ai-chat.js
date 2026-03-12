/**
 * nifty-option-data-ai-chat.js
 * Path: resources/js/nifty-option-data-ai-chat.js
 *
 * AI Analysis + Chat panel
 *
 * FIXES:
 *  - Race condition: setTimeout(300ms) HATAYA. Ab DOMContentLoaded + direct export import use karta hai
 *  - window.openAngelChart aur window.closeModal yahan DEFINE NAHI hote — woh option-chain-chart.js mein hain
 *  - Yahan sirf AI panel aur chat logic hai
 *  - window._lastCandles option-chain-chart.js set karta hai — yahan sirf read karte hain
 */

'use strict';

// ── CSRF ──────────────────────────────────────────────────────────────────────
const _csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

// ── Candle watcher state ──────────────────────────────────────────────────────
let _candleWatchTimer = null;

// ── DOM helpers ───────────────────────────────────────────────────────────────
const _el   = id  => document.getElementById(id);
const _show = id  => { const e = _el(id); if (e) e.style.display = 'flex'; };
const _hide = id  => { const e = _el(id); if (e) e.style.display = 'none'; };
const _esc  = s   => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const _tnow = ()  => new Date().toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit' });

// ─── Wait for candles → auto-trigger AI ───────────────────────────────────────
// option-chain-chart.js window._lastCandles update karta hai jab candles aa jaate hain
function _waitForCandlesAndAnalyze() {
    if (_candleWatchTimer) { clearInterval(_candleWatchTimer); _candleWatchTimer = null; }
    let n = 0;
    _candleWatchTimer = setInterval(() => {
        n++;
        const candles = window._lastCandles || [];
        if (candles.length > 5) {
            clearInterval(_candleWatchTimer);
            _candleWatchTimer = null;
            setTimeout(runAIAnalysis, 300);
        }
        if (n > 40) { // 20s timeout — stop trying
            clearInterval(_candleWatchTimer);
            _candleWatchTimer = null;
        }
    }, 500);
}

// ── Patch window.openAngelChart ───────────────────────────────────────────────
// chart-chart.js apna openAngelChart define karta hai — hum usse WRAP karte hain
// DOMContentLoaded ke baad wrap karo (load order fix)
function _patchChartOpen() {
    const _origOpen = window.openAngelChart;

    window.openAngelChart = function(token, label, exchange, peToken, strike, side, expiry) {
        // Reset AI panel
        _resetAIPanel();
        // Reset chat
        _resetChat(label || '');

        // Stop any pending candle watcher
        if (_candleWatchTimer) { clearInterval(_candleWatchTimer); _candleWatchTimer = null; }
        window._aiAnalyzing = false;
        window._chatTyping  = false;

        // Call original (chart-chart.js) — yeh chart draw karega aur window._lastCandles set karega
        if (typeof _origOpen === 'function') {
            _origOpen(token, label, exchange, peToken, strike, side, expiry);
        }

        // Watch for candles to load, then run AI
        _waitForCandlesAndAnalyze();
    };
}

// Patch closeModal to reset AI state
function _patchChartClose() {
    const _origClose = window.closeModal;
    window.closeModal = function() {
        if (_candleWatchTimer) { clearInterval(_candleWatchTimer); _candleWatchTimer = null; }
        window._aiAnalyzing = false;
        window._chatTyping  = false;
        const dot = _el('chatStatusDot');
        if (dot) { dot.textContent = '● Ready'; dot.style.color = '#16a34a'; }
        if (typeof _origClose === 'function') _origClose();
    };
}

// ── Install patches — wait for chart.js to define its globals ─────────────────
// option-chain-chart.js Vite se import hota hai — window globals synchronously set hote hain
// lekin dono files ek hi bundle mein hain, toh DOMContentLoaded ke baad safe hai
function _install() {
    if (typeof window.openAngelChart === 'function') {
        _patchChartOpen();
        _patchChartClose();
    } else {
        // Agar abhi tak define nahi hua (async load), 50ms baad retry
        setTimeout(_install, 50);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    _install();
});

// ── AI Panel Reset ────────────────────────────────────────────────────────────
function _resetAIPanel() {
    window._aiAnalyzing = false;
    _show('aiWaiting'); _hide('aiSkeleton'); _hide('aiVerdictArea');
    const wp = _el('aiWaiting')?.querySelector('p');
    if (wp) wp.innerHTML = 'Chart load hone ke baad<br><strong style="color:#4f46e5;">AI Analysis</strong> trigger hogi...';
    const btnTxt = _el('aiAnalyzeBtnTxt');
    if (btnTxt) btnTxt.textContent = '⚡ Analyze';
    const btn = _el('aiAnalyzeBtn');
    if (btn) { btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
}

// ── Run AI Analysis ───────────────────────────────────────────────────────────
window.runAIAnalysis = async function() {
    if (window._aiAnalyzing) return;
    window._aiAnalyzing = true;

    _hide('aiWaiting'); _hide('aiVerdictArea'); _show('aiSkeleton');
    const btn    = _el('aiAnalyzeBtn');
    const btnTxt = _el('aiAnalyzeBtnTxt');
    if (btn)    { btn.style.opacity = '.5'; btn.style.cursor = 'not-allowed'; }
    if (btnTxt) btnTxt.textContent = '⏳ Analyzing...';

    try {
        const candles  = (window._lastCandles || []).slice(-30);
        const interval = document.querySelector('[data-iv].bg-indigo-600')?.dataset?.iv || 'FIVE_MINUTE';

        if (!candles.length) throw new Error('Chart data load nahi hua — pehle chart open karo.');

        const res = await fetch('/angel/ai-analyze', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrf() },
            body:    JSON.stringify({
                strike:   window._modalStrike,
                side:     window._modalSide,
                label:    window._modalLabel,
                source:   'Chart',
                isAuto:   false,
                candles,
                interval,
            }),
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success || !json.data) throw new Error(json.message || 'No AI data');
        _renderAI(json.data);

    } catch(err) {
        _hide('aiSkeleton'); _show('aiWaiting');
        const wp = _el('aiWaiting')?.querySelector('p');
        if (wp) wp.innerHTML = `<span style="color:#dc2626;">❌ ${_esc(err.message)}</span><br><small style="color:#64748b;">Retry karo.</small>`;
    }

    window._aiAnalyzing = false;
    if (btn)    { btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
    if (btnTxt) btnTxt.textContent = '🔄 Re-Analyze';
};

// ── Render AI result ──────────────────────────────────────────────────────────
function _renderAI(d) {
    _hide('aiSkeleton'); _hide('aiWaiting'); _show('aiVerdictArea');

    const set = (id, val) => { const e = _el(id); if (e) e.textContent = val || '—'; };
    const setColor = (id, val, color) => {
        const e = _el(id); if (!e) return;
        e.textContent  = val || '—';
        e.style.color  = (color && color !== '#ffffff') ? color : '#0f172a';
    };

    set('aiIcon',         d.icon);
    set('aiVerdictTitle', d.title);
    set('aiConf',         d.confidence);

    const box = _el('aiVerdictBox');
    if (box) {
        box.className = `flex items-center gap-2.5 p-2.5 rounded-lg mb-2 border av-${d.verdict || 'neutral'}`;
    }

    setColor('aiTrendAlign', d.trendAlign,  d.trendAlignColor);
    setColor('aiMomentum',   d.momentum,    d.momentumColor);
    setColor('aiVolSig',     d.volSig,      d.volSigColor);
    setColor('aiRisk',       d.risk,        d.riskColor);

    const lg = _el('aiLevelsGrid');
    if (d.keyLevels?.support || d.keyLevels?.resistance) {
        if (d.keyLevels.support)    { const e = _el('aiSupport'); if (e) e.textContent = d.keyLevels.support; }
        if (d.keyLevels.resistance) { const e = _el('aiResist');  if (e) e.textContent = d.keyLevels.resistance; }
        if (lg) lg.style.display = '';
    } else {
        if (lg) lg.style.display = 'none';
    }

    const ts = _el('aiTimestamp');
    if (ts) ts.textContent = 'Updated: ' + new Date().toLocaleTimeString('en-IN', {
        hour:'2-digit', minute:'2-digit', second:'2-digit',
    });
}

// ── Chat ──────────────────────────────────────────────────────────────────────
function _resetChat(label) {
    window._chatTyping = false;
    const dot = _el('chatStatusDot');
    if (dot) { dot.textContent = '● Ready'; dot.style.color = '#16a34a'; }
    const stuck = document.getElementById('typing-indicator');
    if (stuck) stuck.remove();
    const m = _el('chatMessages');
    if (!m) return;
    m.innerHTML = '';
    _botMsg(`Namaste! <b>${_esc(label)}</b> ka chart open hai.<br>AI analysis ho rahi hai — ya seedha poochho! 👇`);
}

function _userMsg(text) {
    const m = _el('chatMessages');
    if (!m) return;
    const el = document.createElement('div');
    el.className = 'chat-msg user';
    el.innerHTML = `<div class="chat-bubble">${_esc(text)}</div><span class="chat-time">${_tnow()}</span>`;
    m.appendChild(el);
    m.scrollTop = m.scrollHeight;
}

function _botMsg(html) {
    const m = _el('chatMessages');
    if (!m) return;
    const el = document.createElement('div');
    el.className = 'chat-msg bot';
    el.innerHTML = `<div class="chat-bubble">${html}</div><span class="chat-time">${_tnow()}</span>`;
    m.appendChild(el);
    m.scrollTop = m.scrollHeight;
    return el;
}

function _typingMsg() {
    const old = document.getElementById('typing-indicator');
    if (old) old.remove();
    const m = _el('chatMessages');
    if (!m) return null;
    const el = document.createElement('div');
    el.className = 'chat-msg bot';
    el.id        = 'typing-indicator';
    el.innerHTML = `<div class="chat-bubble" style="color:#94a3b8;letter-spacing:3px;">•••</div><span class="chat-time"></span>`;
    m.appendChild(el);
    m.scrollTop = m.scrollHeight;
    return el;
}

window.quickAsk      = t  => { const i = _el('chatInput'); if (i) i.value = t; sendChat(); };
window.handleChatKey = e  => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); } };
window.chatAutoResize= el => { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 72) + 'px'; };

window.sendChat = async function() {
    const inp = _el('chatInput');
    if (!inp) return;
    const text = inp.value.trim();
    if (!text) return;

    // Cancel previous typing indicator if any
    if (window._chatTyping) {
        window._chatTyping = false;
        const old = document.getElementById('typing-indicator');
        if (old) old.remove();
    }

    window._chatTyping  = true;
    const dot           = _el('chatStatusDot');
    const sendBtn       = _el('chatSendBtn');
    if (dot)     { dot.textContent = '● Thinking...'; dot.style.color = '#d97706'; }
    if (sendBtn) sendBtn.style.opacity = '0.5';

    _userMsg(text);
    inp.value      = '';
    inp.style.height = 'auto';
    const typing   = _typingMsg();

    try {
        const res = await fetch('/angel/chart-chat', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrf() },
            body:    JSON.stringify({
                message:  text,
                strike:   window._modalStrike,
                side:     window._modalSide,
                label:    window._modalLabel,
                context:  { candles: (window._lastCandles || []).slice(-20) },
            }),
        });
        const j = await res.json();
        if (typing) {
            const b = typing.querySelector('.chat-bubble');
            if (b) {
                b.innerHTML        = j.reply || j.message || 'Jawab nahi mila.';
                b.style.color      = '#1e293b';
                b.style.letterSpacing = 'normal';
            }
        }
    } catch(e) {
        if (typing) {
            const b = typing.querySelector('.chat-bubble');
            if (b) { b.innerHTML = '❌ Network error. Dobara try karo.'; b.style.color = '#dc2626'; }
        }
    } finally {
        if (typing) {
            const ct = typing.querySelector('.chat-time');
            if (ct) ct.textContent = _tnow();
        }
        if (dot)     { dot.textContent = '● Ready'; dot.style.color = '#16a34a'; }
        if (sendBtn) sendBtn.style.opacity = '1';
        window._chatTyping = false;
        const msgs = _el('chatMessages');
        if (msgs) msgs.scrollTop = 999999;
    }
};
