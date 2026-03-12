/**
 * indicators/scalp-mode.js
 * ScalpMode — Pine Script Exact Match
 *
 * Fixed:
 *   1. volSmooth10 = ta.sma(ta.sma(volume,21), 10)  ← double SMA
 *   2. strongUpTrend = close > hma AND sar < low     ← SAR condition
 *   3. reEntry = strongUpTrend AND close[1]<=hma[1] AND close>hma
 *   4. toIST() — double IST fix (candle.time already IST unix)
 */

'use strict';

import { ema, sma, computeHMA, computeATR } from './indicator-math.js';

// ─── toIST ────────────────────────────────────────────────────────────────────
// candle.time = UTC + 19800 (IST unix) — parseCandles mein shift hua hai
// isliye getUTCHours/Minutes use karo — timezone specify mat karo
// getUTC* on (UTC+19800) timestamp = correct IST hours/minutes
function toIST(istUnixSec) {
    const d  = new Date(istUnixSec * 1000);
    const hh = d.getUTCHours().toString().padStart(2, '0');
    const mm = d.getUTCMinutes().toString().padStart(2, '0');
    return hh + ':' + mm;
}

// ─── PSAR — Pine ta.sar(start, inc, max) ─────────────────────────────────────
function computePSAR(candles, start = 0.02, inc = 0.02, max = 0.2) {
    if (candles.length < 2) return candles.map(() => null);

    const result = new Array(candles.length).fill(null);
    let bull = true;
    let af   = start;
    let ep   = candles[0].low;
    let sar  = candles[0].high;

    result[0] = sar;

    for (let i = 1; i < candles.length; i++) {
        const h  = candles[i].high;
        const l  = candles[i].low;
        const pH = candles[i - 1].high;
        const pL = candles[i - 1].low;

        sar = sar + af * (ep - sar);

        if (bull) {
            sar = Math.min(sar, pL, i > 1 ? candles[i - 2].low : pL);
            if (l < sar) {
                bull = false; sar = ep; ep = l; af = start;
            } else {
                if (h > ep) { ep = h; af = Math.min(af + inc, max); }
            }
        } else {
            sar = Math.max(sar, pH, i > 1 ? candles[i - 2].high : pH);
            if (h > sar) {
                bull = true; sar = ep; ep = h; af = start;
            } else {
                if (l < ep) { ep = l; af = Math.min(af + inc, max); }
            }
        }

        result[i] = sar;
    }

    return result;
}

// ─── Volume Smooth — Pine exact double SMA ────────────────────────────────────
// Pine: volMA21 = ta.sma(volume, 21)
//       volSmooth10 = ta.sma(volMA21, 10)
function computeVolSmooth(volumes) {
    const volMA21     = sma(volumes, 21).map(v => v === null ? 0 : v);
    const volSmooth10 = sma(volMA21, 10).map(v => v === null ? 0 : v);
    return volSmooth10;
}

// ─── Interval detect ─────────────────────────────────────────────────────────
function getIntervalSec(candles) {
    return candles.length > 1
        ? (candles[1].time - candles[0].time)
        : 300;
}

// ─── Scalp Entry Signals ──────────────────────────────────────────────────────
export function computeScalpSignals(candles) {
    if (!candles || candles.length < 30) return { markers: [], dataMap: {} };

    const closes  = candles.map(c => c.close);
    const opens   = candles.map(c => c.open);
    const highs   = candles.map(c => c.high);
    const lows    = candles.map(c => c.low);
    const volumes = candles.map(c => c.volume);

    const hmaArr    = computeHMA(closes, 10);
    const sarArr    = computePSAR(candles, 0.02, 0.02, 0.2);
    const ema6      = ema(volumes, 6);
    const ema9      = ema(volumes, 9);
    const atrArr    = computeATR(candles, 14);
    const volSmooth = computeVolSmooth(volumes);
    const hasSynth  = candles.some(c => c._syntheticVol);

    const intervalSec = getIntervalSec(candles);
    const cooldown    = 3 * intervalSec;

    const markers = [];
    const dataMap = {};

    for (let i = 2; i < candles.length; i++) {
        const hmaV  = hmaArr[i];
        const hmaPV = hmaArr[i - 1];
        const sarV  = sarArr[i];
        if (hmaV === null || hmaPV === null || sarV === null) continue;

        // Pine: crossUp = ta.crossover(close, hma)
        const crossUp = closes[i] > hmaV && closes[i - 1] <= hmaPV;
        if (!crossUp) continue;

        // Pine: hmaSlope = hma - hma[1] > 0
        if (hmaV - hmaPV <= 0) continue;

        // Pine: buySignal = crossUp AND sar < low
        if (sarV >= lows[i]) continue;

        // Pine: volOsc = (ema6 - ema9) / ema9 * 100
        const e6 = ema6[i], e9 = ema9[i];
        let volosc = 0;
        if (e6 !== null && e9 !== null && e9 !== 0)
            volosc = ((e6 - e9) / e9) * 100;

        if (!hasSynth && volosc <= 0) continue;

        // Pine: volHigh = volume > volSmooth10
        const volHigh = volumes[i] > volSmooth[i];
        if (!hasSynth && !volHigh) continue;

        // Cooldown
        const lastTime = markers.length > 0 ? markers[markers.length - 1].time : 0;
        if (candles[i].time - lastTime < cooldown) continue;

        // ATR tfMult
        const tfMult =
            intervalSec <= 300  ? 0.7 :
            intervalSec <= 900  ? 1.0 :
            intervalSec <= 1800 ? 1.2 : 1.4;
        const atrScaled = (atrArr[i] || 0) * tfMult;

        const entryPrice = closes[i];
        const sl   = parseFloat((entryPrice - atrScaled * 1.5).toFixed(2));
        const risk = parseFloat((entryPrice - sl).toFixed(2));
        const tp1  = parseFloat((entryPrice + atrScaled * 1.2).toFixed(2));
        const tp2  = parseFloat((entryPrice + atrScaled * 2.2).toFixed(2));
        const tp3  = parseFloat((entryPrice + atrScaled * 2.8).toFixed(2));

        const riskPts = entryPrice - sl;
        const rr1 = riskPts ? ((tp1 - entryPrice) / riskPts).toFixed(2) : '-';
        const rr2 = riskPts ? ((tp2 - entryPrice) / riskPts).toFixed(2) : '-';
        const rr3 = riskPts ? ((tp3 - entryPrice) / riskPts).toFixed(2) : '-';

        const safety  = hasSynth ? 'SYNTH ⚡' : (volosc > 0 ? 'SAFE ✅' : 'RISKY ⚠️');
        const timeStr = toIST(candles[i].time); // ← IST unix → correct HH:MM

        // ── Outcome detection ─────────────────────────────────────────────────
        let outcome = 'OPEN', outcomeTime = null, tpHitLevel = null;
        for (let j = i + 1; j < candles.length; j++) {
            const tpHit = candles[j].high >= tp1;
            const slHit = candles[j].low  <= sl;
            if (tpHit && slHit) {
                const dTP   = Math.abs(candles[j].open - tp1);
                const dSL   = Math.abs(candles[j].open - sl);
                outcome     = dTP <= dSL ? 'TP_HIT' : 'SL_HIT';
                tpHitLevel  = 'TP1';
                outcomeTime = toIST(candles[j].time);
                break;
            }
            if (tpHit) {
                tpHitLevel  = candles[j].high >= tp3 ? 'TP3'
                            : candles[j].high >= tp2 ? 'TP2' : 'TP1';
                outcome     = 'TP_HIT';
                outcomeTime = toIST(candles[j].time);
                break;
            }
            if (slHit) {
                outcome     = 'SL_HIT';
                outcomeTime = toIST(candles[j].time);
                break;
            }
        }

        const profit = outcome === 'TP_HIT'
            ? ((tpHitLevel === 'TP3' ? tp3 : tpHitLevel === 'TP2' ? tp2 : tp1) - entryPrice).toFixed(2)
            : null;

        dataMap[candles[i].time] = {
            time:      timeStr,
            entry:     entryPrice.toFixed(2),
            sl:        sl.toFixed(2),
            risk:      risk.toFixed(2),
            volosc:    volosc.toFixed(2) + '%',
            safety,
            volume:    volHigh ? 'HIGH 👍' : 'LOW ⚠️',
            tp1:       tp1.toFixed(2),
            tp2:       tp2.toFixed(2),
            tp3:       tp3.toFixed(2),
            rr1, rr2, rr3,
            oscColor:  volosc > 0 ? '#4ade80' : '#f87171',
            safeColor: hasSynth ? '#fb923c' : (volosc > 0 ? '#4ade80' : '#f87171'),
            volColor:  volHigh ? '#4ade80' : '#fb923c',
            outcome, outcomeTime, tpHitLevel, profit,
            loss:      outcome === 'SL_HIT' ? risk.toFixed(2) : null,
            slHit:     outcome === 'SL_HIT',
        };

        markers.push({
            time:     candles[i].time,
            position: 'belowBar',
            color:    outcome === 'TP_HIT' ? '#facc15'
                    : outcome === 'SL_HIT' ? '#ef4444' : '#4ade80',
            shape:    'arrowUp',
            text:     outcome === 'TP_HIT' ? 'TP HIT'
                    : outcome === 'SL_HIT' ? 'SL HIT' : 'ENTRY',
            size:     2,
        });
    }

    return { markers, dataMap };
}

// ─── ScalpMode Overlay — Pine exact ──────────────────────────────────────────
export function computeScalpMode(candles) {
    if (!candles || candles.length < 30) return null;

    const closes  = candles.map(c => c.close);
    const lows    = candles.map(c => c.low);
    const volumes = candles.map(c => c.volume);
    const last    = candles.length - 1;

    const hmaArr    = computeHMA(closes, 10);
    const sarArr    = computePSAR(candles, 0.02, 0.02, 0.2);
    const ema6      = ema(volumes, 6);
    const ema9      = ema(volumes, 9);
    const volSmooth = computeVolSmooth(volumes);

    const hmaV  = hmaArr[last];
    const hmaPV = hmaArr[last - 1];
    const sarV  = sarArr[last];
    if (hmaV === null || hmaPV === null) return null;

    const volosc = (ema6[last] !== null && ema9[last] !== null && ema9[last] !== 0)
        ? ((ema6[last] - ema9[last]) / ema9[last]) * 100 : 0;

    const hasSynth  = candles.some(c => c._syntheticVol);
    const volHigh   = volumes[last] > volSmooth[last];
    const crossUp   = closes[last] > hmaV && closes[last - 1] <= hmaPV;
    const sarBull   = sarV !== null && sarV < lows[last];
    const strongUp  = closes[last] > hmaV && sarBull;
    const buySignal = crossUp && sarBull;

    let signal = 'WAIT';
    if (buySignal && (hasSynth || volosc > 0) && (hasSynth || volHigh))
        signal = 'ENTRY ✅';
    else if (strongUp)
        signal = 'HOLD';

    return {
        signal,
        volosc:      volosc.toFixed(2),
        safety:      hasSynth ? 'SYNTH ⚡' : (volosc > 0 ? 'SAFE ✅' : 'RISKY ⚠️'),
        volume:      volHigh ? 'HIGH 👍' : 'LOW ⚠️',
        signalColor: signal === 'ENTRY ✅' ? '#4ade80' : signal === 'HOLD' ? '#93c5fd' : '#6b7280',
        safeColor:   hasSynth ? '#fb923c' : (volosc > 0 ? '#4ade80' : '#f87171'),
        volColor:    volHigh ? '#4ade80' : '#fb923c',
    };
}

// ─── Apply Markers ────────────────────────────────────────────────────────────
export function applyScalpMarkers(candles, mainSeries, isOn, entryDataMapRef) {
    if (!mainSeries) return;
    if (!isOn) { mainSeries.setMarkers([]); return; }
    const { markers, dataMap } = computeScalpSignals(candles);
    mainSeries.setMarkers(markers);
    entryDataMapRef.current = dataMap;
}

// ─── Update Overlay DOM ───────────────────────────────────────────────────────
export function updateScalpOverlay(candles) {
    const sd = computeScalpMode(candles);
    if (!sd) return;
    setEl('scalp-signal', sd.signal,       sd.signalColor);
    setEl('scalp-volosc', sd.volosc + '%', parseFloat(sd.volosc) > 0 ? '#4ade80' : '#f87171');
    setEl('scalp-safety', sd.safety,       sd.safeColor);
    setEl('scalp-volume', sd.volume,       sd.volColor);
}

function setEl(id, text, color) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.style.color = color; }
}
