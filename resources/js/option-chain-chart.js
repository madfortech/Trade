/**
 * option-chain-chart.js
 * Path: resources/js/option-chain-chart.js
 *
 * LightweightCharts v4 — Candlestick + Line + CE vs PE compare
 * Sab kuch window.* pe expose kiya hai taaki ai-chat.js bhi access kar sake
 */

'use strict';

// ── State ─────────────────────────────────────────────────────────────────────
let _chart        = null;   // LightweightCharts instance
let _mainSeries   = null;   // candlestick ya line series
let _peSeries     = null;   // PE overlay series (compare mode)
let _compareMode  = false;
let _chartType    = 'candlestick'; // 'candlestick' | 'line'
let _liveTimer    = null;
let _retryToken   = null;
let _retryLabel   = null;
let _retryExch    = null;
let _retryPeTok   = null;
let _retryStrike  = null;
let _retrySide    = null;
let _retryExpiry  = null;
let _curInterval  = 'FIVE_MINUTE';

// ── Shared candle store — ai-chat.js reads this ───────────────────────────────
export const _lastCandles = [];  // exported for option-chain.js import

function _setCandles(arr) {
    _lastCandles.length = 0;
    arr.forEach(c => _lastCandles.push(c));
    // window pe bhi rakho taaki ai-chat.js seedha access kar sake
    window._lastCandles = _lastCandles;
}

// ── DOM helpers ───────────────────────────────────────────────────────────────
const _el  = id => document.getElementById(id);
const _show = (id, disp='flex') => { const e = _el(id); if (e) e.style.display = disp; };
const _hide = id  => { const e = _el(id); if (e) e.style.display = 'none'; };

// ── Formatters ────────────────────────────────────────────────────────────────
function _fmtVol(v) {
    if (!v) return '—';
    if (v >= 1e7) return (v / 1e7).toFixed(2) + ' Cr';
    if (v >= 1e5) return (v / 1e5).toFixed(2) + ' L';
    if (v >= 1e3) return (v / 1e3).toFixed(1) + 'K';
    return String(v);
}

// ── Interval helpers ──────────────────────────────────────────────────────────
function _isLiveMarket() {
    const ist = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
    const h = ist.getHours(), m = ist.getMinutes(), d = ist.getDay();
    return d >= 1 && d <= 5
        && (h > 9  || (h === 9  && m >= 15))
        && (h < 15 || (h === 15 && m <= 30));
}

function _liveRefreshMs(interval) {
    return {
        ONE_MINUTE:     20000,
        THREE_MINUTE:   30000,
        FIVE_MINUTE:    45000,
        FIFTEEN_MINUTE: 90000,
        THIRTY_MINUTE:  120000,
        ONE_HOUR:       180000,
        ONE_DAY:        300000,
    }[interval] || 45000;
}

// ── CSRF helper ───────────────────────────────────────────────────────────────
function _csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ── API call ──────────────────────────────────────────────────────────────────
async function _fetchCandles(token, exchange, interval, expiry) {
    const params = new URLSearchParams({
        token,
        exchange,
        interval,
        ...(expiry ? { expiry } : {}),
    });
    const r = await fetch('/angel/candle-data?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _csrf() },
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const json = await r.json();
    if (!json.success) throw new Error(json.message || 'API error');
    return json.data; // [[timestamp, open, high, low, close, volume], ...]
}

// ── Raw candles → LightweightCharts format ────────────────────────────────────
function _toOHLC(raw) {
    // Angel One returns: ["YYYY-MM-DD HH:mm:ss", open, high, low, close, volume]
    return raw
        .map(c => {
            const ts = Math.floor(new Date(c[0]).getTime() / 1000);
            return {
                time:   ts,
                open:   parseFloat(c[1]),
                high:   parseFloat(c[2]),
                low:    parseFloat(c[3]),
                close:  parseFloat(c[4]),
                volume: parseFloat(c[5] || 0),
            };
        })
        .filter(c => c.time > 0 && c.open > 0)
        .sort((a, b) => a.time - b.time);
}

function _toLine(raw) {
    return _toOHLC(raw).map(c => ({ time: c.time, value: c.close }));
}

// ── Create chart ──────────────────────────────────────────────────────────────
function _createChart() {
    const container = _el('mainChart');
    if (!container) return null;

    // Destroy old instance
    if (_chart) { try { _chart.remove(); } catch(e) {} _chart = null; }

    const chart = LightweightCharts.createChart(container, {
        width:  container.clientWidth,
        height: container.clientHeight,
        layout: {
            background: { color: '#ffffff' },
            textColor:  '#334155',
        },
        grid: {
            vertLines:  { color: '#f1f5f9' },
            horzLines:  { color: '#f1f5f9' },
        },
        crosshair: {
            mode: LightweightCharts.CrosshairMode.Normal,
        },
        rightPriceScale: {
            borderColor: '#e2e8f0',
            scaleMargins: { top: 0.1, bottom: 0.1 },
        },
        timeScale: {
            borderColor:      '#e2e8f0',
            timeVisible:      true,
            secondsVisible:   false,
            rightOffset:      5,
            barSpacing:       8,
            minBarSpacing:    2,
            fixLeftEdge:      false,
            lockVisibleTimeRangeOnResize: true,
        },
    });

    // Resize observer
    const ro = new ResizeObserver(entries => {
        for (const entry of entries) {
            const { width, height } = entry.contentRect;
            if (width > 0 && height > 0) chart.applyOptions({ width, height });
        }
    });
    ro.observe(container);

    return chart;
}

// ── Add series ────────────────────────────────────────────────────────────────
function _addCandleSeries(chart) {
    return chart.addCandlestickSeries({
        upColor:          '#16a34a',
        downColor:        '#dc2626',
        borderUpColor:    '#16a34a',
        borderDownColor:  '#dc2626',
        wickUpColor:      '#16a34a',
        wickDownColor:    '#dc2626',
    });
}

function _addLineSeries(chart, color = '#4f46e5') {
    return chart.addLineSeries({
        color,
        lineWidth: 2,
        crosshairMarkerVisible: true,
        lastValueVisible:       true,
        priceLineVisible:       true,
    });
}

// ── OHLC bar update ───────────────────────────────────────────────────────────
function _setupCrosshair(chart, ohlcData) {
    chart.subscribeCrosshairMove(param => {
        const bar = _el('ohlcBar');
        if (!param?.time || !param.seriesData) {
            if (bar) bar.classList.add('hidden');
            return;
        }

        let candle = null;
        param.seriesData.forEach(v => { if (v && 'open' in v) candle = v; });
        if (!candle) return;

        if (bar) {
            bar.classList.remove('hidden');
            const ts = new Date(param.time * 1000).toLocaleString('en-IN', {
                timeZone: 'Asia/Kolkata', day:'2-digit', month:'short',
                hour:'2-digit', minute:'2-digit', hour12: false,
            });
            const chg = candle.close - candle.open;
            const pct = candle.open > 0 ? ((chg / candle.open) * 100).toFixed(2) : 0;

            const set = (id, val) => { const e = _el(id); if (e) e.textContent = val; };
            set('ohlcLabel', ts);
            set('oVal', candle.open.toFixed(2));
            set('hVal', candle.high.toFixed(2));
            set('lVal', candle.low.toFixed(2));
            set('cVal', candle.close.toFixed(2));
            set('volVal', _fmtVol(candle.volume));

            const tag = _el('changeTag');
            if (tag) {
                tag.textContent = `${chg >= 0 ? '+' : ''}${chg.toFixed(2)} (${pct}%)`;
                tag.style.color = chg >= 0 ? '#16a34a' : '#dc2626';
            }
        }
    });
}

// ── Main fetch + render ───────────────────────────────────────────────────────
export async function fetchAndRender(token, label, exchange, peToken, strike, side, expiry, interval) {
    interval = interval || _curInterval;

    // Store retry params
    _retryToken  = token;
    _retryLabel  = label;
    _retryExch   = exchange;
    _retryPeTok  = peToken;
    _retryStrike = strike;
    _retrySide   = side;
    _retryExpiry = expiry;

    // Stop old live timer
    if (_liveTimer) { clearInterval(_liveTimer); _liveTimer = null; }

    // Show loader, hide error
    _show('chartLoader');
    _hide('chartError');
    _hide('ohlcBar');

    // Clear candle store
    _setCandles([]);

    try {
        const raw = await _fetchCandles(token, exchange, interval, expiry);
        if (!raw || !raw.length) throw new Error('Koi data nahi mila. Market band ho sakta hai ya symbol galat hai.');

        const ohlcData = _toOHLC(raw);
        if (!ohlcData.length) throw new Error('Candle data empty — timestamp parse error.');

        // Store candles for AI
        _setCandles(ohlcData);

        // ── Build chart ────────────────────────────────────────────────────
        _chart = _createChart();
        if (!_chart) throw new Error('Chart container nahi mila.');

        _compareMode = false;
        _peSeries    = null;

        if (_chartType === 'line') {
            _mainSeries = _addLineSeries(_chart, '#4f46e5');
            _mainSeries.setData(_toLine(raw));
        } else {
            _mainSeries = _addCandleSeries(_chart);
            _mainSeries.setData(ohlcData);
        }

        _setupCrosshair(_chart, ohlcData);

        // Fit + scroll to end
        _chart.timeScale().fitContent();

        // ── Footer ────────────────────────────────────────────────────────
        const cc = _el('candleCount');
        if (cc) cc.textContent = `${ohlcData.length} candles · ${interval.replace('_', ' ')}`;

        // ── Live chip ─────────────────────────────────────────────────────
        const live = _el('liveChip');
        if (live) {
            if (_isLiveMarket()) { live.classList.remove('hidden'); }
            else                 { live.classList.add('hidden'); }
        }

        // ── Compare button ────────────────────────────────────────────────
        const cmpBtn = _el('compareBtn');
        if (cmpBtn) {
            if (peToken) { cmpBtn.classList.remove('hidden'); }
            else         { cmpBtn.classList.add('hidden'); }
        }

        // ── Hide loader ───────────────────────────────────────────────────
        _hide('chartLoader');

        // ── Live auto-refresh ─────────────────────────────────────────────
        if (_isLiveMarket()) {
            _liveTimer = setInterval(async () => {
                if (!_isLiveMarket()) { clearInterval(_liveTimer); _liveTimer = null; return; }
                try {
                    const rraw   = await _fetchCandles(token, exchange, _curInterval, expiry);
                    const rOhlc  = _toOHLC(rraw);
                    if (!rOhlc.length) return;
                    _setCandles(rOhlc);
                    if (_chartType === 'line') {
                        _mainSeries?.setData(rOhlc.map(c => ({ time: c.time, value: c.close })));
                    } else {
                        _mainSeries?.setData(rOhlc);
                    }
                    const lc = _el('candleCount');
                    if (lc) lc.textContent = `${rOhlc.length} candles · ${_curInterval.replace('_',' ')} · live`;
                } catch(e) { /* silent */ }
            }, _liveRefreshMs(_curInterval));
        }

    } catch(err) {
        _hide('chartLoader');
        _show('chartError');
        const em = _el('errMsg');
        if (em) em.textContent = err.message;
        console.error('Chart error:', err);
    }
}

// ── openAngelChart — GLOBAL ───────────────────────────────────────────────────
// Blade mein buttons yahi call karte hain
window.openAngelChart = function(token, label, exchange, peToken, strike, side, expiry) {
    expiry = expiry || '';

    // Reset compare label
    const cl = _el('compareLabel');
    if (cl) cl.classList.add('hidden');

    // Modal title
    const title = _el('modalTitle');
    if (title) title.textContent = label || '';

    // Store globals for ai-chat.js
    window._modalStrike = strike  || null;
    window._modalSide   = side    || null;
    window._modalLabel  = label   || '';
    window._modalExpiry = expiry;

    // Show modal
    const modal = _el('chartModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Active interval highlight
    document.querySelectorAll('.iv-btn').forEach(b => {
        b.classList.toggle('bg-indigo-600', b.dataset.iv === _curInterval);
        b.classList.toggle('text-white',    b.dataset.iv === _curInterval);
        b.classList.toggle('bg-gray-100',   b.dataset.iv !== _curInterval);
        b.classList.toggle('text-gray-500', b.dataset.iv !== _curInterval);
    });

    // Render chart
    fetchAndRender(token, label, exchange, peToken, strike, side, expiry, _curInterval);
};

// ── closeModal — GLOBAL ───────────────────────────────────────────────────────
window.closeModal = function() {
    if (_liveTimer) { clearInterval(_liveTimer); _liveTimer = null; }
    _compareMode = false;

    const modal = _el('chartModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    _hide('ohlcBar');
    _hide('chartError');
    _show('chartLoader');

    // Destroy chart
    if (_chart) { try { _chart.remove(); } catch(e) {} _chart = null; }
    _mainSeries = null;
    _peSeries   = null;

    // Reset candles
    _setCandles([]);
};

// ── handleBackdropClick — GLOBAL ─────────────────────────────────────────────
window.handleBackdropClick = function(event) {
    if (event.target === _el('chartModal')) window.closeModal();
};

// ── changeInterval — GLOBAL ───────────────────────────────────────────────────
window.changeInterval = function(interval) {
    _curInterval = interval;

    // Update button styles
    document.querySelectorAll('.iv-btn').forEach(b => {
        const active = b.dataset.iv === interval;
        b.classList.toggle('bg-indigo-600', active);
        b.classList.toggle('text-white',    active);
        b.classList.toggle('bg-gray-100',   !active);
        b.classList.toggle('text-gray-500', !active);
    });

    // Re-fetch with new interval
    if (_retryToken) {
        fetchAndRender(_retryToken, _retryLabel, _retryExch, _retryPeTok, _retryStrike, _retrySide, _retryExpiry, interval);
    }
};

// ── retryLoad — GLOBAL ───────────────────────────────────────────────────────
window.retryLoad = function() {
    if (_retryToken) {
        fetchAndRender(_retryToken, _retryLabel, _retryExch, _retryPeTok, _retryStrike, _retrySide, _retryExpiry, _curInterval);
    }
};

// ── setChartType — GLOBAL ─────────────────────────────────────────────────────
window.setChartType = function(type) {
    if (_chartType === type) return;
    _chartType = type;

    const bc = _el('btnCandle'), bl = _el('btnLine');
    if (bc && bl) {
        if (type === 'candlestick') {
            bc.classList.add('bg-indigo-600', 'text-white');
            bc.classList.remove('text-gray-500');
            bl.classList.remove('bg-indigo-600', 'text-white');
            bl.classList.add('text-gray-500');
        } else {
            bl.classList.add('bg-indigo-600', 'text-white');
            bl.classList.remove('text-gray-500');
            bc.classList.remove('bg-indigo-600', 'text-white');
            bc.classList.add('text-gray-500');
        }
    }

    if (_retryToken) {
        fetchAndRender(_retryToken, _retryLabel, _retryExch, _retryPeTok, _retryStrike, _retrySide, _retryExpiry, _curInterval);
    }
};

// ── toggleCompare — GLOBAL (CE vs PE overlay) ─────────────────────────────────
window.toggleCompare = async function() {
    if (!_chart || !_mainSeries) return;
    _compareMode = !_compareMode;

    const cmpBtn = _el('compareBtn');
    const cl     = _el('compareLabel');

    if (!_compareMode) {
        // Remove PE overlay
        if (_peSeries) { try { _chart.removeSeries(_peSeries); } catch(e) {} _peSeries = null; }
        if (cmpBtn) { cmpBtn.classList.remove('bg-purple-600','text-white'); cmpBtn.classList.add('bg-gray-100','text-gray-500'); }
        if (cl) cl.classList.add('hidden');
        return;
    }

    // Add PE overlay
    if (!_retryPeTok) { _compareMode = false; return; }

    if (cmpBtn) { cmpBtn.classList.add('bg-purple-600','text-white'); cmpBtn.classList.remove('bg-gray-100','text-gray-500'); }
    if (cl) cl.classList.remove('hidden');

    try {
        const raw    = await _fetchCandles(_retryPeTok, _retryExch, _curInterval, _retryExpiry);
        const ohlc   = _toOHLC(raw);
        _peSeries    = _addLineSeries(_chart, '#dc2626');
        _peSeries.setData(ohlc.map(c => ({ time: c.time, value: c.close })));

        // Make CE line
        if (_chartType !== 'line') {
            // Convert main series to line for clean compare
            const ceData = _lastCandles.map(c => ({ time: c.time, value: c.close }));
            try { _chart.removeSeries(_mainSeries); } catch(e) {}
            _mainSeries = _addLineSeries(_chart, '#16a34a');
            _mainSeries.setData(ceData);
        }
    } catch(e) {
        _compareMode = false;
        if (cmpBtn) { cmpBtn.classList.remove('bg-purple-600','text-white'); cmpBtn.classList.add('bg-gray-100','text-gray-500'); }
        if (cl) cl.classList.add('hidden');
        console.error('Compare PE error:', e);
    }
};

// ── takeScreenshot — GLOBAL ───────────────────────────────────────────────────
window.takeScreenshot = function() {
    const box = _el('modalBox');
    if (!box || typeof html2canvas === 'undefined') {
        alert('Screenshot library load nahi hui. Ctrl+Shift+S try karo.');
        return;
    }
    html2canvas(box, { backgroundColor: '#f8fafc', scale: 1.5 }).then(canvas => {
        const a    = document.createElement('a');
        a.href     = canvas.toDataURL('image/png');
        a.download = `nifty-chart-${_retryLabel?.replace(/\s+/g,'-') || 'chart'}-${Date.now()}.png`;
        a.click();
    });
};
