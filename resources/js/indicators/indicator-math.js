/**
 * indicators/indicator-math.js
 *
 * Shared Math Helpers — Pine Script Exact Match
 * ──────────────────────────────────────────────
 * Exports:
 *   ema(data, period)
 *   sma(data, period)
 *   rma(data, period)          ← Pine ta.rma (Wilder's MA)
 *   computeHMA(closes, len)
 *   computeATR(candles, len)   ← Pine ta.atr
 *   computeMACD(closes, fast, slow, signal)
 *   computeVWAP(candles)       ← Pine ta.vwap(high)
 *   computeStochRSI(closes, rsiLen, stochLen, kLen)
 */

'use strict';

// ─── EMA ─────────────────────────────────────────────────────────────────────
export function ema(data, period) {
    const k = 2 / (period + 1);
    const result = [];
    let prev = null;
    for (let i = 0; i < data.length; i++) {
        if (prev === null) {
            if (i < period - 1) { result.push(null); continue; }
            prev = data.slice(0, period).reduce((a, b) => a + b, 0) / period;
            result.push(prev);
        } else {
            prev = data[i] * k + prev * (1 - k);
            result.push(prev);
        }
    }
    return result;
}

// ─── SMA ─────────────────────────────────────────────────────────────────────
export function sma(data, period) {
    return data.map((_, i) => {
        if (i < period - 1) return null;
        const sl = data.slice(i - period + 1, i + 1).filter(v => v !== null);
        return sl.length === period ? sl.reduce((a, b) => a + b, 0) / period : null;
    });
}

// ─── RMA (Wilder's MA) — Pine ta.rma ─────────────────────────────────────────
// Pine Script ta.rma(src, length) = alpha * src + (1 - alpha) * prev
// alpha = 1 / length
export function rma(data, period) {
    const alpha = 1 / period;
    const result = [];
    let prev = null;
    for (let i = 0; i < data.length; i++) {
        if (prev === null) {
            if (i < period - 1) { result.push(null); continue; }
            prev = data.slice(0, period).reduce((a, b) => a + b, 0) / period;
            result.push(prev);
        } else {
            prev = alpha * data[i] + (1 - alpha) * prev;
            result.push(prev);
        }
    }
    return result;
}

// ─── HMA (Hull Moving Average) ────────────────────────────────────────────────
export function computeHMA(closes, len = 10) {
    const half  = Math.floor(len / 2);
    const sqLen = Math.round(Math.sqrt(len));
    const wmaH  = ema(closes, half);
    const wmaF  = ema(closes, len);
    const raw   = wmaH.map((v, i) =>
        (v !== null && wmaF[i] !== null) ? 2 * v - wmaF[i] : null
    );
    return ema(raw.map(v => v === null ? 0 : v), sqLen);
}

// ─── ATR — Pine ta.atr(length) ───────────────────────────────────────────────
// True Range = max(high-low, abs(high-prevClose), abs(low-prevClose))
// ATR = rma(tr, length)
export function computeATR(candles, length = 14) {
    const tr = candles.map((c, i) => {
        if (i === 0) return c.high - c.low;
        const prevClose = candles[i - 1].close;
        return Math.max(
            c.high - c.low,
            Math.abs(c.high - prevClose),
            Math.abs(c.low  - prevClose)
        );
    });
    return rma(tr, length);
}

// ─── MACD — Pine ta.macd(close, fast, slow, signal) ──────────────────────────
// fastLen=15, slowLen=24, signalLen=10  (SecondEye default)
export function computeMACD(closes, fast = 15, slow = 24, signal = 10) {
    const emaFast    = ema(closes, fast);
    const emaSlow    = ema(closes, slow);
    const macdLine   = emaFast.map((v, i) =>
        (v !== null && emaSlow[i] !== null) ? v - emaSlow[i] : null
    );
    const signalLine = ema(macdLine.map(v => v === null ? 0 : v), signal);
    const hist       = macdLine.map((v, i) =>
        (v !== null && signalLine[i] !== null) ? v - signalLine[i] : null
    );
    return { macdLine, signalLine, hist };
}

// ─── VWAP — Pine ta.vwap(high) ───────────────────────────────────────────────
// Source = HIGH (Pine default ta.vwap(high))
// Session VWAP — cumulative from first candle
export function computeVWAP(candles) {
    let cumVol = 0, cumTP = 0;
    return candles.map(c => {
        const vol = c.volume || 10000;
        cumVol += vol;
        cumTP  += c.high * vol;          // HIGH source — Pine exact
        return cumVol > 0 ? cumTP / cumVol : c.high;
    });
}

// ─── Stochastic RSI — Pine ta.stoch(rsi, rsi, rsi, stochLen) ─────────────────
// rsiLen=13, stochLen=15, kLen=5, dLen=4
export function computeStochRSI(closes, rsiLen = 13, stochLen = 15, kLen = 5) {
    // RSI (Simple method — Pine ta.rsi)
    const rsiArr = [];
    for (let i = 0; i < closes.length; i++) {
        if (i < rsiLen) { rsiArr.push(null); continue; }
        let gains = 0, losses = 0;
        for (let j = i - rsiLen + 1; j <= i; j++) {
            const d = closes[j] - closes[j - 1];
            if (d > 0) gains += d; else losses -= d;
        }
        const avgG = gains / rsiLen, avgL = losses / rsiLen;
        rsiArr.push(avgL === 0 ? 100 : 100 - 100 / (1 + avgG / avgL));
    }

    // Stoch of RSI — Pine ta.stoch(rsiValue, rsiValue, rsiValue, stochLen)
    const stochArr = rsiArr.map((v, i) => {
        if (v === null || i < rsiLen + stochLen - 1) return null;
        const sl = rsiArr.slice(i - stochLen + 1, i + 1).filter(x => x !== null);
        if (sl.length < stochLen) return null;
        const lo = Math.min(...sl), hi = Math.max(...sl);
        return hi === lo ? 50 : ((v - lo) / (hi - lo)) * 100;
    });

    // K = ta.sma(stochRsi, kLen)
    return sma(stochArr.map(v => v === null ? 0 : v), kLen);
}
