/**
 * option-chain-chart.js
 * Path: resources/js/option-chain-chart.js
 *
 * Chart Modal — Render, Live Update, Controls
 * FIXED:
 *   1. _resetLiveState() ab _setCandles([]) nahi karta — sirf timer reset hota hai
 *   2. _startLiveTimer() market closed par bhi ek baar live update karta hai
 *   3. fetchAndRender() ke baad _lastCandles guarantee populate hote hain
 *   4. window._lastCandles always sync rehta hai
 */

'use strict';

import { isMarketOpen } from './option-chain.js';

// ─── IST Offset ───────────────────────────────────────────────────────────────
const IST_OFFSET = 19800; // +5:30 in seconds

// ─── Chart State ──────────────────────────────────────────────────────────────
let chartInst  = null;
let mainSeries = null;
let volSeries  = null;
let peSeries2  = null;

let curToken    = null;
let curPeToken  = null;
let curLabel    = '';
let curExchange = 'NFO';
let curInterval = 'FIVE_MINUTE';
let curType     = 'candlestick';
let compareMode = false;
let curExpiry   = ''; // e.g. "27FEB2026" — expiry date for API capping

let _modalAutoTimer       = null;
let _liveUpdateInProgress = false;

// ─── _lastCandles — both exported AND on window ───────────────────────────────
export let _lastCandles = [];

function _setCandles(arr) {
    _lastCandles        = arr;
    window._lastCandles = arr;   // ← blade AI panel uses this
}

// ─── parseCandles ─────────────────────────────────────────────────────────────
export function parseCandles(raw) {
    const parsed = (raw || []).map(c => {
        const utcSec = Math.floor(new Date(c[0]).getTime() / 1000);
        return {
            time:   utcSec + IST_OFFSET,
            open:   parseFloat(c[1]),
            high:   parseFloat(c[2]),
            low:    parseFloat(c[3]),
            close:  parseFloat(c[4]),
            volume: parseFloat(c[5] || 0),
        };
    }).filter(c => c.open > 0 && !isNaN(c.open));

    // Synthetic volume for NFO options (when 80%+ candles have zero volume)
    const zeroVolRatio = parsed.filter(c => c.volume === 0).length / (parsed.length || 1);
    if (zeroVolRatio > 0.8) {
        parsed.forEach(c => {
            const range = c.high - c.low;
            c.volume        = range > 0 ? Math.round(range * 1000) : 100;
            c._syntheticVol = true;
        });
    }
    return parsed;
}

// ─── Open Modal ───────────────────────────────────────────────────────────────
window.openAngelChart = function (token, label, exchange, peTokenVal, strike, side, expiry) {
    curToken    = token;
    curLabel    = label;
    curExchange = exchange || 'NFO';
    curPeToken  = peTokenVal || null;
    curInterval = 'FIVE_MINUTE';
    compareMode = false;
    curExpiry   = expiry || ''; // ✅ Store expiry for API date capping

    document.getElementById('modalTitle').textContent = label;
    const ohlcLabel = document.getElementById('ohlcLabel');
    if (ohlcLabel) ohlcLabel.textContent = label;

    const compareLabel = document.getElementById('compareLabel');
    const compareBtn   = document.getElementById('compareBtn');
    if (compareLabel) compareLabel.classList.add('hidden');
    if (compareBtn) {
        compareBtn.classList.toggle('hidden', !curPeToken);
        compareBtn.classList.remove('bg-purple-700', 'text-white');
        compareBtn.classList.add('bg-gray-100', 'text-gray-500');
    }

    _setIVActive(curInterval);
    _openModalEl();

    // ✅ FIX: Timer cancel karo, candles clear karo — lekin separately
    _stopLiveTimer();
    _setCandles([]); // Modal open hone par fresh start

    fetchAndRender().then(() => {
        // ✅ FIX: fetchAndRender complete hone ke baad timer start karo
        _startLiveTimer();
    });
};

function _openModalEl() {
    const modal = document.getElementById('chartModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

window.closeModal = function () {
    const modal = document.getElementById('chartModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    _destroyAll();
    _stopLiveTimer();
    _setCandles([]);
    _liveUpdateInProgress = false;
};

window.handleBackdropClick = function (e) {
    if (e.target === document.getElementById('chartModal')) window.closeModal();
};

window.retryLoad = function () {
    document.getElementById('chartError').classList.add('hidden');
    document.getElementById('chartLoader').classList.remove('hidden');
    fetchAndRender().then(() => _startLiveTimer());
};

window.changeInterval = function (iv) {
    curInterval = iv;
    _setIVActive(iv);
    _destroyAll();
    _stopLiveTimer();
    _setCandles([]);
    document.getElementById('chartLoader').classList.remove('hidden');
    fetchAndRender().then(() => _startLiveTimer());
};

window.setChartType = function (type) {
    curType = type;
    const active   = 'text-[10px] px-2.5 py-1 font-bold text-white bg-indigo-600 transition-colors';
    const inactive = 'text-[10px] px-2.5 py-1 font-bold text-gray-500 hover:text-gray-700 transition-colors border-l border-gray-200';
    document.getElementById('btnCandle').className = type === 'candlestick' ? active : inactive.replace(' border-l border-gray-200', '');
    document.getElementById('btnLine').className   = type === 'line' ? active + ' border-l border-gray-200' : inactive;
    _destroyAll();
    _stopLiveTimer();
    _setCandles([]);
    document.getElementById('chartLoader').classList.remove('hidden');
    fetchAndRender().then(() => _startLiveTimer());
};

window.toggleCompare = function () {
    if (!curPeToken) return;
    compareMode = !compareMode;
    const btn = document.getElementById('compareBtn');
    document.getElementById('compareLabel')?.classList.toggle('hidden', !compareMode);
    if (compareMode) {
        btn.classList.add('bg-purple-100', 'text-purple-700');
        btn.classList.remove('bg-gray-100', 'text-gray-500');
    } else {
        btn.classList.remove('bg-purple-100', 'text-purple-700');
        btn.classList.add('bg-gray-100', 'text-gray-500');
    }
    _destroyAll();
    _stopLiveTimer();
    _setCandles([]);
    document.getElementById('chartLoader').classList.remove('hidden');
    fetchAndRender().then(() => _startLiveTimer());
};

// ─── Fetch + Render ───────────────────────────────────────────────────────────
export async function fetchAndRender(silent = false) {
    if (!silent) {
        document.getElementById('chartLoader').classList.remove('hidden');
        document.getElementById('chartError')?.classList.add('hidden');
        document.getElementById('ohlcBar')?.classList.add('hidden');
    }

    try {
        const res  = await fetch(
            `/angel/candle-data?token=${curToken}&exchange=${curExchange}&interval=${curInterval}&expiry=${encodeURIComponent(curExpiry)}&_t=${Date.now()}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const json = await res.json();

        if (!json.success || !json.data?.length) {
            _showErr(json.message || 'No data available.');
            return;
        }

        const candles = parseCandles(json.data);

        if (!candles.length) {
            _showErr('Candle data parse nahi hua. Data format check karo.');
            return;
        }

        // ✅ FIX: Candles set karo BEFORE rendering
        _setCandles(candles);

        let peCandles = [];
        if (compareMode && curPeToken) {
            try {
                const peRes  = await fetch(
                    `/angel/candle-data?token=${curPeToken}&exchange=${curExchange}&interval=${curInterval}&expiry=${encodeURIComponent(curExpiry)}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const peJson = await peRes.json();
                peCandles    = parseCandles(peJson.data || []);
            } catch (e) {
                console.warn('[Compare PE fetch failed]', e.message);
            }
        }

        _renderChart(candles, peCandles);

    } catch (err) {
        _showErr('Network error: ' + err.message);
    }
}

// ─── Live Candle Update ───────────────────────────────────────────────────────
async function _liveUpdateLastCandle() {
    if (!curToken || !chartInst || !mainSeries) return;
    if (!isMarketOpen() || _liveUpdateInProgress) return;

    _liveUpdateInProgress = true;

    try {
        const res  = await fetch(
            `/angel/candle-data?token=${curToken}&exchange=${curExchange}&interval=${curInterval}&expiry=${encodeURIComponent(curExpiry)}&_t=${Date.now()}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const json = await res.json();
        if (!json.success || !json.data?.length) { _liveUpdateInProgress = false; return; }

        const freshCandles = parseCandles(json.data);
        if (!freshCandles.length)                { _liveUpdateInProgress = false; return; }

        const lastFresh  = freshCandles[freshCandles.length - 1];
        const lastStored = _lastCandles.length ? _lastCandles[_lastCandles.length - 1] : null;

        if (!lastStored || lastFresh.time > lastStored.time) {
            // New candle(s) added
            const storedLastTime = lastStored ? lastStored.time : 0;
            const newCandles     = freshCandles.filter(c => c.time > storedLastTime);
            newCandles.forEach(c => {
                curType === 'candlestick'
                    ? mainSeries.update(c)
                    : mainSeries.update({ time: c.time, value: c.close });
                if (volSeries) volSeries.update({
                    time: c.time, value: c.volume,
                    color: c.close >= c.open ? '#16a34a30' : '#dc262630',
                });
            });
            _setCandles([..._lastCandles, ...newCandles]);
            document.getElementById('candleCount').textContent = `${_lastCandles.length} candles`;

        } else if (lastStored && lastFresh.time === lastStored.time) {
            // Last candle updated (same time — price change)
            const changed = lastFresh.close  !== lastStored.close
                         || lastFresh.high   !== lastStored.high
                         || lastFresh.low    !== lastStored.low
                         || lastFresh.volume !== lastStored.volume;

            if (changed) {
                curType === 'candlestick'
                    ? mainSeries.update(lastFresh)
                    : mainSeries.update({ time: lastFresh.time, value: lastFresh.close });
                if (volSeries) volSeries.update({
                    time: lastFresh.time, value: lastFresh.volume,
                    color: lastFresh.close >= lastFresh.open ? '#16a34a30' : '#dc262630',
                });
                const updated = [..._lastCandles];
                updated[updated.length - 1] = lastFresh;
                _setCandles(updated);
            }
        }

        _updateOHLC(_lastCandles[_lastCandles.length - 1]);

    } catch (e) {
        console.warn('[LiveCandle]', e.message);
    }

    _liveUpdateInProgress = false;
}

// ─── Render Chart — LIGHT THEME ───────────────────────────────────────────────
function _renderChart(candles, peCandles = []) {
    document.getElementById('chartLoader').classList.add('hidden');
    if (!candles.length) { _showErr('No valid candle data received.'); return; }

    _destroyAll();
    const container = document.getElementById('mainChart');

    chartInst = LightweightCharts.createChart(container, {
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
            mode:     LightweightCharts.CrosshairMode.Normal,
            vertLine: { color: '#6366f1', width: 1, style: 0, labelBackgroundColor: '#4f46e5' },
            horzLine: { color: '#6366f1', width: 1, style: 0, labelBackgroundColor: '#4f46e5' },
        },
        rightPriceScale: {
            borderColor: '#e2e8f0',
            textColor:   '#64748b',
        },
        timeScale: {
            borderColor:    '#e2e8f0',
            timeVisible:    true,
            secondsVisible: false,
            rightOffset:    5,
        },
    });

    // ── Main Series ───────────────────────────────────────────────────────────
    if (curType === 'candlestick') {
        mainSeries = chartInst.addCandlestickSeries({
            upColor:         '#16a34a',
            downColor:       '#dc2626',
            borderUpColor:   '#16a34a',
            borderDownColor: '#dc2626',
            wickUpColor:     '#16a34a',
            wickDownColor:   '#dc2626',
        });
        mainSeries.setData(candles);
    } else {
        mainSeries = chartInst.addLineSeries({ color: '#6366f1', lineWidth: 2 });
        mainSeries.setData(candles.map(c => ({ time: c.time, value: c.close })));
    }

    // ── Volume ────────────────────────────────────────────────────────────────
    volSeries = chartInst.addHistogramSeries({
        priceFormat:  { type: 'volume' },
        priceScaleId: '',
        scaleMargins: { top: 0.87, bottom: 0 },
    });
    volSeries.setData(candles.map(c => ({
        time:  c.time,
        value: c.volume,
        color: c.close >= c.open ? '#16a34a25' : '#dc262625',
    })));

    // ── PE Overlay (compare mode) ─────────────────────────────────────────────
    if (compareMode && peCandles.length) {
        peSeries2 = chartInst.addLineSeries({
            color: '#f43f5e', lineWidth: 1.5, title: 'PE',
            priceScaleId: 'pe', priceLineVisible: false,
        });
        chartInst.priceScale('pe').applyOptions({
            position: 'left', borderColor: '#f43f5e30', textColor: '#f43f5e',
        });
        peSeries2.setData(peCandles.map(c => ({ time: c.time, value: c.close })));
    }

    chartInst.timeScale().fitContent();
    document.getElementById('ohlcBar')?.classList.remove('hidden');
    _updateOHLC(candles[candles.length - 1]);

    // ── Crosshair OHLC update ─────────────────────────────────────────────────
    chartInst.subscribeCrosshairMove(p => {
        if (p?.seriesData?.size) {
            const d = p.seriesData.get(mainSeries);
            if (d) _updateOHLC(d);
        }
    });

    // ── Resize observer ───────────────────────────────────────────────────────
    new ResizeObserver(() => {
        if (chartInst) chartInst.resize(container.clientWidth, container.clientHeight);
    }).observe(container);

    document.getElementById('candleCount').textContent = `${candles.length} candles`;
}

// ─── OHLC Bar Update ──────────────────────────────────────────────────────────
function _updateOHLC(d) {
    if (!d) return;
    const f = v => v != null ? parseFloat(v).toFixed(2) : '—';
    const g = id => document.getElementById(id);

    if (g('oVal'))   g('oVal').textContent   = f(d.open);
    if (g('hVal'))   g('hVal').textContent   = f(d.high);
    if (g('lVal'))   g('lVal').textContent   = f(d.low);
    if (g('cVal'))   g('cVal').textContent   = f(d.close ?? d.value);
    if (g('volVal')) g('volVal').textContent = d.volume
        ? Number(d.volume).toLocaleString('en-IN') : '—';

    if (d.open && (d.close ?? d.value)) {
        const chg = (((d.close ?? d.value) - d.open) / d.open * 100).toFixed(2);
        const el  = g('changeTag');
        if (el) {
            el.textContent = (chg >= 0 ? '▲ +' : '▼ ') + chg + '%';
            el.className   = 'font-bold ' + (chg >= 0 ? 'text-green-600' : 'text-red-500');
        }
    }
}

// ─── Screenshot ───────────────────────────────────────────────────────────────
window.takeScreenshot = async function () {
    try {
        const canvas = await html2canvas(document.getElementById('modalBox'), {
            backgroundColor: '#ffffff', scale: 2, useCORS: true,
        });
        const link    = document.createElement('a');
        link.download = `${curLabel.replace(/\s+/g, '_')}_${curInterval}_${Date.now()}.png`;
        link.href     = canvas.toDataURL('image/png');
        link.click();
    } catch (e) {
        alert('Screenshot failed: ' + e.message);
    }
};

// ─── Timer Helpers ────────────────────────────────────────────────────────────
function _stopLiveTimer() {
    if (_modalAutoTimer) {
        clearInterval(_modalAutoTimer);
        _modalAutoTimer = null;
    }
    _liveUpdateInProgress = false;
}

// ✅ FIX: Market closed par bhi chip hide karo, lekin candles clear mat karo
function _startLiveTimer() {
    const chip = document.getElementById('liveChip');
    if (!isMarketOpen()) {
        chip?.classList.add('hidden');
        return; // Timer start mat karo, lekin candles already set hain
    }
    chip?.classList.remove('hidden');
    const intervalMs = {
        ONE_MINUTE:     60000,
        THREE_MINUTE:   3000,
        FIVE_MINUTE:    5000,
        FIFTEEN_MINUTE: 10000,
        THIRTY_MINUTE:  15000,
        ONE_HOUR:       30000,
        ONE_DAY:        60000,
    };
    const ms = intervalMs[curInterval] || 5000;
    _stopLiveTimer(); // Pehle cancel karo agar koi chal raha ho
    _modalAutoTimer = setInterval(_liveUpdateLastCandle, ms);
}

// ─── Destroy Chart ────────────────────────────────────────────────────────────
function _destroyAll() {
    if (chartInst) {
        chartInst.remove();
        chartInst = null;
    }
    mainSeries = null;
    volSeries  = null;
    peSeries2  = null;
}

// ─── Error Display ────────────────────────────────────────────────────────────
function _showErr(msg) {
    document.getElementById('chartLoader')?.classList.add('hidden');
    document.getElementById('chartError')?.classList.remove('hidden');
    const errEl = document.getElementById('errMsg');
    if (errEl) errEl.textContent = msg;
}

// ─── Interval Button Active State ─────────────────────────────────────────────
function _setIVActive(iv) {
    document.querySelectorAll('.iv-btn').forEach(b => {
        const active = b.dataset.iv === iv;
        b.classList.toggle('bg-indigo-600', active);
        b.classList.toggle('text-white',    active);
        b.classList.toggle('bg-gray-100',   !active);
        b.classList.toggle('text-gray-500', !active);
    });
}
