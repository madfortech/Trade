/**
 * option-chain-chart.js
 * Path: resources/js/option-chain-chart.js
 *
 * LightweightCharts v4 — Candlestick + Line + CE vs PE compare
 * UPDATED: Live tick update har 2 seconds (last candle real-time)
 * FIX: IST timestamp parsing — explicit +05:30 offset for Angel One API
 */

'use strict';

// ── State ─────────────────────────────────────────────────────────────────────
let _chart         = null;
let _mainSeries    = null;
let _peSeries      = null;
let _compareMode   = false;
let _chartType     = 'candlestick';
let _liveTimer     = null;
let _liveTickTimer = null;
let _retryToken    = null;
let _retryLabel    = null;
let _retryExch     = null;
let _retryPeTok    = null;
let _retryStrike   = null;
let _retrySide     = null;
let _retryExpiry   = null;
let _curInterval   = 'FIVE_MINUTE';

// ── Shared candle store — ai-chat.js reads this ───────────────────────────────
const _lastCandles = [];


function _setCandles(arr) {
    _lastCandles.length = 0;
    arr.forEach(c => _lastCandles.push(c));
    window._lastCandles = _lastCandles;
}

// ── DOM helpers ───────────────────────────────────────────────────────────────
const _el   = id => document.getElementById(id);
const _show = (id, disp = 'flex') => { const e = _el(id); if (e) e.style.display = disp; };
const _hide = id => { const e = _el(id); if (e) e.style.display = 'none'; };

// ── Formatters ────────────────────────────────────────────────────────────────
function _fmtVol(v) {
    if (!v) return '—';
    if (v >= 1e7) return (v / 1e7).toFixed(2) + ' Cr';
    if (v >= 1e5) return (v / 1e5).toFixed(2) + ' L';
    if (v >= 1e3) return (v / 1e3).toFixed(1) + 'K';
    return String(v);
}

// ── Market helpers ────────────────────────────────────────────────────────────
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

// ── CSRF ──────────────────────────────────────────────────────────────────────
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
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': _csrf(),
        },
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const json = await r.json();
    if (!json.success) throw new Error(json.message || 'API error');
    return json.data;
}

// ── Raw → LightweightCharts format ───────────────────────────────────────────
// FIX: Angel One API "2026-03-27 15:25" format ko IST ke saath correctly parse karo
function _toOHLC(raw) {
    if (!raw || !Array.isArray(raw)) {
        console.error('Raw data is not an array:', raw);
        return [];
    }

    return raw
        .map(c => {
            try {
                let timestamp;

                if (typeof c[0] === 'string') {
                    let dateStr = c[0];

                    // Step 1: "2026-03-27 15:25" → "2026-03-27T15:25"
                    if (!dateStr.includes('T')) {
                        dateStr = dateStr.replace(' ', 'T');
                    }

                    // Step 2: agar koi timezone info nahi hai toh IST offset lagao
                    // "Z" (UTC) ya "+" (already has offset) → skip
                    if (!dateStr.includes('+') && !dateStr.includes('Z')) {
                        dateStr += '+05:30';
                    }

                    const d = new Date(dateStr);

                    // Invalid date guard
                    if (isNaN(d.getTime())) {
                        console.warn('Invalid date skipped:', c[0]);
                        return null;
                    }

                    timestamp = Math.floor(d.getTime() / 1000);
                } else {
                    // Already a numeric timestamp
                    timestamp = parseInt(c[0]);
                }

                if (isNaN(timestamp) || timestamp <= 0) return null;

                const open   = parseFloat(c[1]);
                const high   = parseFloat(c[2]);
                const low    = parseFloat(c[3]);
                const close  = parseFloat(c[4]);
                const volume = parseFloat(c[5] || 0);

                // Basic OHLC sanity check
                if (isNaN(open) || isNaN(high) || isNaN(low) || isNaN(close)) return null;
                if (open <= 0 || close <= 0) return null;

                return { time: timestamp, open, high, low, close, volume };

            } catch (e) {
                console.warn('Row parsing failed:', c, e);
                return null;
            }
        })
        .filter(Boolean)
        .sort((a, b) => a.time - b.time);
}

function _toLine(raw) {
    return _toOHLC(raw).map(c => ({ time: c.time, value: c.close }));
}

// ── Create chart ──────────────────────────────────────────────────────────────
function _createChart() {
    const container = _el('mainChart');
    if (!container) return null;

    if (_chart) {
        try { _chart.remove(); } catch (e) {}
        _chart = null;
    }

    const chart = LightweightCharts.createChart(container, {
        width:  container.clientWidth,
        height: container.clientHeight,
        layout: {
            background: { color: '#ffffff' },
            textColor:  '#334155',
        },
        grid: {
            vertLines: { color: '#f1f5f9' },
            horzLines: { color: '#f1f5f9' },
        },
        crosshair: {
            mode: LightweightCharts.CrosshairMode.Normal,
        },
        rightPriceScale: {
            borderColor:  '#e2e8f0',
            scaleMargins: { top: 0.1, bottom: 0.1 },
        },
        timeScale: {
            borderColor:    '#e2e8f0',
            timeVisible:    true,
            secondsVisible: false,
            rightOffset:    5,
            barSpacing:     8,
            minBarSpacing:  2,
            fixLeftEdge:    false,
            lockVisibleTimeRangeOnResize: true,
        },
    });

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
        upColor:         '#16a34a',
        downColor:       '#dc2626',
        borderUpColor:   '#16a34a',
        borderDownColor: '#dc2626',
        wickUpColor:     '#16a34a',
        wickDownColor:   '#dc2626',
    });
}

function _addLineSeries(chart, color = '#4f46e5') {
    return chart.addLineSeries({
        color,
        lineWidth:              2,
        crosshairMarkerVisible: true,
        lastValueVisible:       true,
        priceLineVisible:       true,
    });
}

// ── OHLC crosshair bar ────────────────────────────────────────────────────────
function _setupCrosshair(chart) {
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
                timeZone:  'Asia/Kolkata',
                day:       '2-digit',
                month:     'short',
                hour:      '2-digit',
                minute:    '2-digit',
                hour12:    false,
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

// ── Stop all timers ───────────────────────────────────────────────────────────
function _stopAllTimers() {
    if (_liveTimer)     { clearInterval(_liveTimer);     _liveTimer     = null; }
    if (_liveTickTimer) { clearInterval(_liveTickTimer); _liveTickTimer = null; }
}

// ── Live tick: har 2 seconds LTP fetch → last candle update ──────────────────
function _startTickTimer(token, exchange, expiry) {
    if (_liveTickTimer) { clearInterval(_liveTickTimer); _liveTickTimer = null; }

    _liveTickTimer = setInterval(async () => {
        if (!_isLiveMarket()) {
            clearInterval(_liveTickTimer);
            _liveTickTimer = null;
            return;
        }
        if (!_mainSeries) return;

        try {
            const r = await fetch(
                '/angel/chain-refresh?expiry=' + encodeURIComponent(expiry || ''),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const json = await r.json();
            if (!json.success || !json.data) return;

            const strike = window._modalStrike;
            const side   = (window._modalSide || 'CE').toLowerCase();
            if (!strike || !json.data[strike] || !json.data[strike][side]) return;

            const ltp = parseFloat(json.data[strike][side].ltp || 0);
            if (ltp <= 0) return;

            const candles = window._lastCandles;
            if (!candles || !candles.length) return;

            const last = candles[candles.length - 1];

            const updatedCandle = {
                time:   last.time,
                open:   last.open,
                high:   Math.max(last.high, ltp),
                low:    Math.min(last.low,  ltp),
                close:  ltp,
                volume: last.volume,
            };

            if (_chartType === 'line') {
                _mainSeries.update({ time: last.time, value: ltp });
            } else {
                _mainSeries.update(updatedCandle);
            }

            // Memory update
            candles[candles.length - 1] = updatedCandle;

            // Footer live indicator
            const lc = _el('candleCount');
            if (lc) {
                const t = new Date().toLocaleTimeString('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    hour:     '2-digit',
                    minute:   '2-digit',
                    second:   '2-digit',
                    hour12:   false,
                });
                lc.textContent = `${candles.length} candles · ${_curInterval.replace('_', ' ')} · ● ${t}`;
            }

        } catch (e) { /* silent — network flicker pe crash nahi */ }

    }, 2000);
}

// ── Full candle reload: nayi candle banti hai tab ─────────────────────────────
function _startFullReloadTimer(token, exchange, expiry) {
    if (_liveTimer) { clearInterval(_liveTimer); _liveTimer = null; }

    _liveTimer = setInterval(async () => {
        if (!_isLiveMarket()) { clearInterval(_liveTimer); _liveTimer = null; return; }
        try {
            const rraw  = await _fetchCandles(token, exchange, _curInterval, expiry);
            const rOhlc = _toOHLC(rraw);
            if (!rOhlc.length) return;

            _setCandles(rOhlc);

            if (_chartType === 'line') {
                _mainSeries?.setData(rOhlc.map(c => ({ time: c.time, value: c.close })));
            } else {
                _mainSeries?.setData(rOhlc);
            }
        } catch (e) { /* silent */ }
    }, _liveRefreshMs(_curInterval));
}

// ── Main fetch + render ───────────────────────────────────────────────────────
async function fetchAndRender(token, label, exchange, peToken, strike, side, expiry, interval) {

    interval = interval || _curInterval;

    _retryToken  = token;
    _retryLabel  = label;
    _retryExch   = exchange;
    _retryPeTok  = peToken;
    _retryStrike = strike;
    _retrySide   = side;
    _retryExpiry = expiry;

    _stopAllTimers();

    _show('chartLoader');
    _hide('chartError');
    _hide('ohlcBar');
    _setCandles([]);

    try {
        const raw = await _fetchCandles(token, exchange, interval, expiry);
        if (!raw || !raw.length) throw new Error('Koi data nahi mila. Market band ho sakta hai ya symbol galat hai.');

        const ohlcData = _toOHLC(raw);
        if (!ohlcData.length) throw new Error('Candle data empty — timestamp parse error.');

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

        _setupCrosshair(_chart);
        _chart.timeScale().fitContent();

        // Footer
        const cc = _el('candleCount');
        if (cc) cc.textContent = `${ohlcData.length} candles · ${interval.replace('_', ' ')}`;

        // Live chip
        const live = _el('liveChip');
        if (live) {
            _isLiveMarket() ? live.classList.remove('hidden') : live.classList.add('hidden');
        }

        // Compare button
        const cmpBtn = _el('compareBtn');
        if (cmpBtn) {
            peToken ? cmpBtn.classList.remove('hidden') : cmpBtn.classList.add('hidden');
        }

        _hide('chartLoader');

        // ── Start live timers ──────────────────────────────────────────────
        if (_isLiveMarket()) {
            _startTickTimer(token, exchange, expiry);
            _startFullReloadTimer(token, exchange, expiry);
        }

    } catch (err) {
        _hide('chartLoader');
        _show('chartError');
        const em = _el('errMsg');
        if (em) em.textContent = err.message;
        console.error('Chart error:', err);
    }
}

// ── openAngelChart — GLOBAL ───────────────────────────────────────────────────
window.openAngelChart = function (token, label, exchange, peToken, strike, side, expiry) {
    expiry = expiry || '';

    const cl = _el('compareLabel');
    if (cl) cl.classList.add('hidden');

    const title = _el('modalTitle');
    if (title) title.textContent = label || '';

    window._modalStrike = strike  || null;
    window._modalSide   = side    || null;
    window._modalLabel  = label   || '';
    window._modalExpiry = expiry;

    const modal = _el('chartModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    document.querySelectorAll('.iv-btn').forEach(b => {
        b.classList.toggle('bg-indigo-600', b.dataset.iv === _curInterval);
        b.classList.toggle('text-white',    b.dataset.iv === _curInterval);
        b.classList.toggle('bg-gray-100',   b.dataset.iv !== _curInterval);
        b.classList.toggle('text-gray-500', b.dataset.iv !== _curInterval);
    });

    fetchAndRender(token, label, exchange, peToken, strike, side, expiry, _curInterval);
};

// ── closeModal — GLOBAL ───────────────────────────────────────────────────────
window.closeModal = function () {
    _stopAllTimers();
    _compareMode = false;

    const modal = _el('chartModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    _hide('ohlcBar');
    _hide('chartError');
    _show('chartLoader');

    if (_chart) { try { _chart.remove(); } catch (e) {} _chart = null; }
    _mainSeries = null;
    _peSeries   = null;

    _setCandles([]);
};

// ── handleBackdropClick — GLOBAL ─────────────────────────────────────────────
window.handleBackdropClick = function (event) {
    if (event.target === _el('chartModal')) window.closeModal();
};

// ── changeInterval — GLOBAL ───────────────────────────────────────────────────
window.changeInterval = function (interval) {
    _curInterval = interval;

    document.querySelectorAll('.iv-btn').forEach(b => {
        const active = b.dataset.iv === interval;
        b.classList.toggle('bg-indigo-600', active);
        b.classList.toggle('text-white',    active);
        b.classList.toggle('bg-gray-100',   !active);
        b.classList.toggle('text-gray-500', !active);
    });

    if (_retryToken) {
        fetchAndRender(_retryToken, _retryLabel, _retryExch, _retryPeTok, _retryStrike, _retrySide, _retryExpiry, interval);
    }
};

// ── retryLoad — GLOBAL ───────────────────────────────────────────────────────
window.retryLoad = function () {
    if (_retryToken) {
        fetchAndRender(_retryToken, _retryLabel, _retryExch, _retryPeTok, _retryStrike, _retrySide, _retryExpiry, _curInterval);
    }
};

// ── setChartType — GLOBAL ─────────────────────────────────────────────────────
window.setChartType = function (type) {
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

// ── toggleCompare — GLOBAL ────────────────────────────────────────────────────
window.toggleCompare = async function () {
    if (!_chart || !_mainSeries) return;
    _compareMode = !_compareMode;

    const cmpBtn = _el('compareBtn');
    const cl     = _el('compareLabel');

    if (!_compareMode) {
        if (_peSeries) { try { _chart.removeSeries(_peSeries); } catch (e) {} _peSeries = null; }
        if (cmpBtn) { cmpBtn.classList.remove('bg-purple-600', 'text-white'); cmpBtn.classList.add('bg-gray-100', 'text-gray-500'); }
        if (cl) cl.classList.add('hidden');
        return;
    }

    if (!_retryPeTok) { _compareMode = false; return; }

    if (cmpBtn) { cmpBtn.classList.add('bg-purple-600', 'text-white'); cmpBtn.classList.remove('bg-gray-100', 'text-gray-500'); }
    if (cl) cl.classList.remove('hidden');

    try {
        const raw  = await _fetchCandles(_retryPeTok, _retryExch, _curInterval, _retryExpiry);
        const ohlc = _toOHLC(raw);
        _peSeries  = _addLineSeries(_chart, '#dc2626');
        _peSeries.setData(ohlc.map(c => ({ time: c.time, value: c.close })));

        if (_chartType !== 'line') {
            const ceData = _lastCandles.map(c => ({ time: c.time, value: c.close }));
            try { _chart.removeSeries(_mainSeries); } catch (e) {}
            _mainSeries = _addLineSeries(_chart, '#16a34a');
            _mainSeries.setData(ceData);
        }
    } catch (e) {
        _compareMode = false;
        if (cmpBtn) { cmpBtn.classList.remove('bg-purple-600', 'text-white'); cmpBtn.classList.add('bg-gray-100', 'text-gray-500'); }
        if (cl) cl.classList.add('hidden');
        console.error('Compare PE error:', e);
    }
};

// ── takeScreenshot — GLOBAL ───────────────────────────────────────────────────
window.takeScreenshot = function () {
    const box = _el('modalBox');
    if (!box || typeof html2canvas === 'undefined') {
        alert('Screenshot library load nahi hui. Ctrl+Shift+S try karo.');
        return;
    }
    html2canvas(box, { backgroundColor: '#f8fafc', scale: 1.5 }).then(canvas => {
        const a    = document.createElement('a');
        a.href     = canvas.toDataURL('image/png');
        a.download = `nifty-chart-${_retryLabel?.replace(/\s+/g, '-') || 'chart'}-${Date.now()}.png`;
        a.click();
    });
};

window.fetchAndRender = fetchAndRender;
window._lastCandles = _lastCandles;
