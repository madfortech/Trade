/**
 * indicators/short-cover.js
 *
 * SecondEye: ShortCover — Pine Script Exact Match
 * ─────────────────────────────────────────────────
 * Pine Source: "SecondEye • HedgeV1.1" — shortCoverBuy logic
 *
 * Pine Logic:
 *   downTrendSC     = maFast < maSlow AND close < maSlow
 *   swingHighSC     = ta.highest(high, 5)[1]   ← previous bar ka swing
 *   breakStructureSC= close > swingHighSC
 *   momentumSC      = (close - open) > atrV * 0.6
 *   vwapReclaimSC   = close > vwap
 *   shortCoverBuy   = all above AND NOT in trade
 */

'use strict';

import { sma, computeVWAP, computeATR } from './indicator-math.js';

// ─── ShortCover Bias Compute ──────────────────────────────────────────────────
export function computeShortCoverBias(candles) {
    if (!candles || candles.length < 30) return null;

    const closes = candles.map(c => c.close);
    const opens  = candles.map(c => c.open);
    const highs  = candles.map(c => c.high);
    const last   = candles.length - 1;

    // Pine: maFast = ta.sma(close, 10), maSlow = ta.sma(close, 27)
    const maFastArr = sma(closes, 10);
    const maSlowArr = sma(closes, 27);
    const vwapArr   = computeVWAP(candles);   // ta.vwap(high)
    const atrArr    = computeATR(candles, 14); // ta.atr(14)

    const cl   = closes[last];
    const op   = opens[last];
    const mf   = maFastArr[last];
    const ms   = maSlowArr[last];
    const vw   = vwapArr[last];
    const atrV = atrArr[last];

    if (!mf || !ms || !vw || !atrV) return null;

    // Pine: tfMult (5m chart default = 0.7)
    // JS mein interval detect karo
    const intervalSec = candles.length > 1
        ? (candles[last].time - candles[last - 1].time)
        : 300;
    const tfMult =
        intervalSec <= 300  ? 0.7 :   // <=5m
        intervalSec <= 900  ? 1.0 :   // <=15m
        intervalSec <= 1800 ? 1.2 :   // <=30m
        1.4;                           // 1h+

    const atrScaled = atrV * tfMult;

    // Pine: downTrendSC = maFast < maSlow and close < maSlow
    const downTrend = mf < ms && cl < ms;

    // Pine: swingHighSC = ta.highest(high, 5)[1]
    // [1] = previous bar, isliye last-1 se peeche 5 bars
    const swing5 = highs.slice(Math.max(0, last - 5), last);  // last excluded = [1]
    const swingHighSC = Math.max(...swing5);

    // Pine: breakStructureSC = close > swingHighSC
    const breakStructure = cl > swingHighSC;

    // Pine: momentumSC = (close - open) > atrV * 0.6
    const momentum = (cl - op) > atrScaled * 0.6;

    // Pine: vwapReclaimSC = close > vwap
    const vwapReclaim = cl > vw;

    // Pine: shortCoverBuy = downTrendSC and breakStructureSC and momentumSC and close > vwapV
    const shortCoverReady = downTrend && breakStructure && momentum && vwapReclaim;

    // MA Bias (HedgeBias engine)
    // bullCond1-5 / bearCond1-5 → score >= 3
    const slope = maFastArr[last] - maFastArr[last - 1];  // Pine: slope = basis - basis[1]

    const bullScore =
        (mf > ms      ? 1 : 0) +   // bullCond1
        (cl > vw      ? 1 : 0) +   // bullCond5 (VWAP)
        (slope > 0    ? 1 : 0) +   // bullCond3 (slope)
        (cl > maFastArr[last] ? 1 : 0);  // bullCond2 approx

    const bearScore =
        (mf < ms      ? 1 : 0) +
        (cl < vw      ? 1 : 0) +
        (slope < 0    ? 1 : 0) +
        (cl < maFastArr[last] ? 1 : 0);

    const bias = bullScore >= 3 ? 'BULL' : bearScore >= 3 ? 'BEAR' : 'NEUTRAL';

    return {
        downTrend,
        breakStructure,
        momentum,
        vwapReclaim,
        shortCoverReady,
        bias,
        ma:     mf > ms ? 'OK ✅' : 'NO ❌',
        vwap:   vwapReclaim ? 'ABOVE ✅' : 'BELOW ❌',
        signal: shortCoverReady ? 'SHORT COVER 🔥' : 'NORMAL',

        maColor:     mf > ms ? '#4ade80' : '#f87171',
        vwapColor:   vwapReclaim ? '#4ade80' : '#f87171',
        biasColor:   bias === 'BULL' ? '#4ade80' : bias === 'BEAR' ? '#f87171' : '#fb923c',
        signalColor: shortCoverReady ? '#4ade80' : '#6b7280',
    };
}

// ─── Update Overlay DOM ───────────────────────────────────────────────────────
export function updateShortCoverOverlay(candles) {
    const sc = computeShortCoverBias(candles);
    if (!sc) return;
    setEl('sc-ma',     sc.ma,     sc.maColor);
    setEl('sc-vwap',   sc.vwap,   sc.vwapColor);
    setEl('sc-bias',   sc.bias,   sc.biasColor);
    setEl('sc-signal', sc.signal, sc.signalColor);
}

function setEl(id, text, color) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.style.color = color; }
}
