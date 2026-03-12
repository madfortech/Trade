const _csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
let _candleWatchTimer = null;

// ── Override openAngelChart ────────────────────────────────────────────────
// Vite bundle load hone ke baad override install karo
function _installOverrides() {
    const _origOpen  = window.openAngelChart;
    const _origClose = window.closeModal;

    // ✅ 7 arguments: token, label, exchange, peToken, strike, side, expiry
    window.openAngelChart = function(token, label, exchange, peToken, strike, side, expiry) {
        window._modalStrike  = strike  || null;
        window._modalSide    = side    || null;
        window._modalLabel   = label   || '';
        window._modalExpiry  = expiry  || '';   // ✅ store expiry
        window._lastCandles  = [];
        window._aiAnalyzing  = false;
        window._chatTyping   = false;

        if (_candleWatchTimer) { clearInterval(_candleWatchTimer); _candleWatchTimer = null; }

        _resetAIPanel();
        _resetChat(label);

        // ✅ origOpen ko 4 args pass karo (chart-chart.js mein curExpiry already set hoga)
        // lekin pehle window.curExpiry set karo taaki chart file use kar sake
        // Actually chart file mein openAngelChart override hoga — isliye seedha call karo
        if (typeof _origOpen === 'function') {
            _origOpen(token, label, exchange, peToken, strike, side, expiry);
        }

        _waitForCandlesAndAnalyze();
    };

    window.closeModal = function() {
        if (_candleWatchTimer) { clearInterval(_candleWatchTimer); _candleWatchTimer = null; }
        window._lastCandles = [];
        window._aiAnalyzing = false;
        window._chatTyping  = false;
        const dot = _el('chatStatusDot');
        if (dot) { dot.textContent = '● Ready'; dot.style.color = '#16a34a'; }
        if (typeof _origClose === 'function') _origClose();
    };
}

// Vite bundle async load hota hai — 300ms wait karo
setTimeout(_installOverrides, 300);

// ── Candle watcher → AI auto-trigger ──────────────────────────────────────
function _waitForCandlesAndAnalyze() {
    let n = 0;
    _candleWatchTimer = setInterval(() => {
        n++;
        if ((window._lastCandles || []).length > 5) {
            clearInterval(_candleWatchTimer);
            _candleWatchTimer = null;
            setTimeout(runAIAnalysis, 400);
        }
        if (n > 40) { // 20s timeout
            clearInterval(_candleWatchTimer);
            _candleWatchTimer = null;
        }
    }, 500);
}

// ── AI Panel ──────────────────────────────────────────────────────────────
function _resetAIPanel() {
    window._aiAnalyzing = false;
    _show('aiWaiting'); _hide('aiSkeleton'); _hide('aiVerdictArea');
    const wp = _el('aiWaiting')?.querySelector('p');
    if (wp) wp.innerHTML = 'Chart load hone ke baad<br><strong style="color:#4f46e5;">AI Analysis</strong> trigger hogi...';
    if (_el('aiAnalyzeBtnTxt')) _el('aiAnalyzeBtnTxt').textContent = '⚡ Analyze';
    const btn = _el('aiAnalyzeBtn');
    if (btn) Object.assign(btn.style, { opacity:'1', cursor:'pointer' });
}

window.runAIAnalysis = async function() {
    if (window._aiAnalyzing) return;
    window._aiAnalyzing = true;
    _hide('aiWaiting'); _hide('aiVerdictArea'); _show('aiSkeleton');
    const btn = _el('aiAnalyzeBtn');
    if (btn) Object.assign(btn.style, { opacity:'.5', cursor:'not-allowed' });
    if (_el('aiAnalyzeBtnTxt')) _el('aiAnalyzeBtnTxt').textContent = '⏳ Analyzing...';

    try {
        const candles  = (window._lastCandles || []).slice(-30);
        const interval = document.querySelector('[data-iv].bg-indigo-600')?.dataset?.iv || 'FIVE_MINUTE';
        if (!candles.length) throw new Error('Chart data load nahi hua.');

        const res = await fetch('/angel/ai-analyze', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':_csrf() },
            body: JSON.stringify({
                strike: window._modalStrike,
                side:   window._modalSide,
                label:  window._modalLabel,
                source: 'Chart',
                isAuto: false,
                candles,
                interval,
            }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success || !json.data) throw new Error(json.message || 'No data');
        _renderAI(json.data);

    } catch(err) {
        _hide('aiSkeleton'); _show('aiWaiting');
        const wp = _el('aiWaiting')?.querySelector('p');
        if (wp) wp.innerHTML = '<span style="color:#dc2626;">❌ ' + err.message + '</span><br><small style="color:#64748b;">Retry karo.</small>';
    }

    window._aiAnalyzing = false;
    const btn2 = _el('aiAnalyzeBtn');
    if (btn2) Object.assign(btn2.style, { opacity:'1', cursor:'pointer' });
    if (_el('aiAnalyzeBtnTxt')) _el('aiAnalyzeBtnTxt').textContent = '🔄 Re-Analyze';
};

function _renderAI(d) {
    _hide('aiSkeleton'); _show('aiVerdictArea');
    _el('aiIcon').textContent         = d.icon       || '📊';
    _el('aiVerdictTitle').textContent = d.title      || '--';
    _el('aiConf').textContent         = d.confidence || '--';
    _el('aiVerdictBox').className     = `flex items-center gap-2.5 p-2.5 rounded-lg mb-2 border av-${d.verdict || 'neutral'}`;
    _setM('aiTrendAlign', d.trendAlign, d.trendAlignColor);
    _setM('aiMomentum',   d.momentum,   d.momentumColor);
    _setM('aiVolSig',     d.volSig,     d.volSigColor);
    _setM('aiRisk',       d.risk,       d.riskColor);
    const lg = _el('aiLevelsGrid');
    if (d.keyLevels?.support || d.keyLevels?.resistance) {
        if (d.keyLevels.support)    _el('aiSupport').textContent = d.keyLevels.support;
        if (d.keyLevels.resistance) _el('aiResist').textContent  = d.keyLevels.resistance;
        if (lg) lg.style.display = '';
    } else { if (lg) lg.style.display = 'none'; }
    if (_el('aiTimestamp')) _el('aiTimestamp').textContent = 'Updated: ' +
        new Date().toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}

function _setM(id, val, color) {
    const el = _el(id); if (!el) return;
    el.textContent = val || '--';
    el.style.color = (color && color !== '#ffffff') ? color : '#0f172a';
}

// ── Chat ──────────────────────────────────────────────────────────────────
function _resetChat(label) {
    window._chatTyping = false;
    const dot = _el('chatStatusDot');
    if (dot) { dot.textContent = '● Ready'; dot.style.color = '#16a34a'; }
    const stuck = document.getElementById('typing-indicator');
    if (stuck) stuck.remove();
    const m = _el('chatMessages'); if (!m) return;
    m.innerHTML = '';
    _botMsg(`Namaste! <b>${label}</b> ka chart open hai.<br>AI analysis ho rahi hai — ya seedha poochho! 👇`);
}

function _userMsg(text) {
    const m = _el('chatMessages'), el = document.createElement('div');
    el.className = 'chat-msg user';
    el.innerHTML = `<div class="chat-bubble">${_esc(text)}</div><span class="chat-time">${_tnow()}</span>`;
    m.appendChild(el); m.scrollTop = m.scrollHeight;
}
function _botMsg(html) {
    const m = _el('chatMessages'), el = document.createElement('div');
    el.className = 'chat-msg bot';
    el.innerHTML = `<div class="chat-bubble">${html}</div><span class="chat-time">${_tnow()}</span>`;
    m.appendChild(el); m.scrollTop = m.scrollHeight; return el;
}
function _typingMsg() {
    const old = document.getElementById('typing-indicator');
    if (old) old.remove();
    const m = _el('chatMessages'), el = document.createElement('div');
    el.className = 'chat-msg bot'; el.id = 'typing-indicator';
    el.innerHTML = `<div class="chat-bubble" style="color:#94a3b8;letter-spacing:3px;">•••</div><span class="chat-time"></span>`;
    m.appendChild(el); m.scrollTop = m.scrollHeight; return el;
}

window.quickAsk       = t  => { _el('chatInput').value = t; sendChat(); };
window.handleChatKey  = e  => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); } };
window.chatAutoResize = el => { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 72) + 'px'; };

window.sendChat = async function() {
    const inp = _el('chatInput'), text = inp.value.trim();
    if (!text) return;
    if (window._chatTyping) {
        window._chatTyping = false;
        const old = document.getElementById('typing-indicator');
        if (old) old.remove();
    }
    window._chatTyping = true;
    const dot = _el('chatStatusDot');
    if (dot) { dot.textContent = '● Thinking...'; dot.style.color = '#d97706'; }
    const sendBtn = _el('chatSendBtn');
    if (sendBtn) sendBtn.style.opacity = '0.5';
    _userMsg(text); inp.value = ''; inp.style.height = 'auto';
    const typing = _typingMsg();
    try {
        const res = await fetch('/angel/chart-chat', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':_csrf() },
            body: JSON.stringify({
                message: text,
                strike:  window._modalStrike,
                side:    window._modalSide,
                label:   window._modalLabel,
                context: { candles: (window._lastCandles || []).slice(-20) },
            }),
        });
        const j = await res.json();
        const b = typing.querySelector('.chat-bubble');
        if (b) { b.innerHTML = j.reply || j.message || 'Jawab nahi mila.'; b.style.color = '#1e293b'; b.style.letterSpacing = 'normal'; }
    } catch(e) {
        const b = typing.querySelector('.chat-bubble');
        if (b) { b.innerHTML = '❌ Network error. Dobara try karo.'; b.style.color = '#dc2626'; }
    } finally {
        const ct = typing.querySelector('.chat-time');
        if (ct) ct.textContent = _tnow();
        if (dot) { dot.textContent = '● Ready'; dot.style.color = '#16a34a'; }
        if (sendBtn) sendBtn.style.opacity = '1';
        window._chatTyping = false;
        const msgs = _el('chatMessages');
        if (msgs) msgs.scrollTop = 999999;
    }
};

// ── Helpers ───────────────────────────────────────────────────────────────
function _el(id)   { return document.getElementById(id); }
function _show(id) { const e = _el(id); if (e) e.style.display = 'flex'; }
function _hide(id) { const e = _el(id); if (e) e.style.display = 'none'; }
function _esc(s)   { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function _tnow()   { return new Date().toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit' }); }

document.addEventListener('keydown', e => { if (e.key === 'Escape') window.closeModal?.(); });
