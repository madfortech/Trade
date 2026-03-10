/**
 * indicators/trend-dashboard.js
 *
 * SecondEye: MACD + VWAP Trend Dashboard — Pine Script Exact Match
 * ─────────────────────────────────────────────────────────────────
 * Pine Source: "SecondEye: MACD + VWAP Trend DashboardV1.1"
 *
 * Logic:
 *   bullish = close > vwap(high) AND macd > signal AND hist > 0
 *   bearish = close < vwap(high) AND macd < signal AND hist < 0
 *   strongTrend = trendCount>=3 AND histStrength > sma(abs(hist),5) AND vwapDist>0.05%
 *   reversal = BOUNCE MODE / DROP MODE
 */

'use strict';

import { computeMACD, computeVWAP, computeStochRSI } from './indicator-math.js';

// ─── Trend Dashboard Compute ──────────────────────────────────────────────────
export function computeTrendDashboard(candles) {
    if (!candles || candles.length < 30) return null;

    const closes = candles.map(c => c.close);
    const last   = candles.length - 1;

    // Pine: ta.macd(close, 15, 24, 10)
    const { macdLine, signalLine, hist } = computeMACD(closes, 15, 24, 10);

    // Pine: ta.vwap(high) — HIGH source
    const vwapArr = computeVWAP(candles);

    // Pine: ta.stoch(rsi, rsi, rsi, 15) → sma(k, 5)
    const kArr = computeStochRSI(closes, 13, 15, 5);

    const macd  = macdLine[last];
    const sig   = signalLine[last];
    const h     = hist[last];
    const hPrev = hist[last - 1];
    const vwap  = vwapArr[last];
    const cl    = closes[last];
    const k     = kArr[last];

    if (macd === null || vwap === null || k === null) return null;

    // ── Trend Direction — Pine exact ──────────────────────────────────────────
    // bullish = close > vwapHigh and macdLine > signalLine and hist > 0
    // bearish = close < vwapHigh and macdLine < signalLine and hist < 0
    const bullish = cl > vwap && macd > sig && h > 0;
    const bearish = cl < vwap && macd < sig && h < 0;

    let trend = 'SIDE';
    if (bullish)      trend = 'UP';
    else if (bearish) trend = 'DOWN';

    // ── Trend Count — Pine exact ──────────────────────────────────────────────
    // newTrend == currentTrend → trendCount += 1, else reset to 1
    let trendCount = 1;
    for (let i = last - 1; i >= 0; i--) {
        const m  = macdLine[i];
        const s2 = signalLine[i];
        const hh = hist[i];
        const vw = vwapArr[i];
        const cl2 = closes[i];
        if (m === null) break;

        const bull2 = cl2 > vw && m > s2 && hh > 0;
        const bear2 = cl2 < vw && m < s2 && hh < 0;
        let t2 = 'SIDE';
        if (bull2)       t2 = 'UP';
        else if (bear2)  t2 = 'DOWN';

        if (t2 === trend) trendCount++;
        else break;
    }

    // ── Trend Strength — Pine exact ───────────────────────────────────────────
    // strongTrend = (trendCount >= 3) AND
    //              (histStrength > ta.sma(math.abs(hist), 5)) AND
    //              (vwapDistance > 0.05)
    const vwapDistance  = Math.abs(cl - vwap) / cl * 100;
    const histStrength  = Math.abs(h);
    const histSMA5slice = hist.slice(Math.max(0, last - 4), last + 1)
                              .filter(v => v !== null)
                              .map(v => Math.abs(v));
    const histSMA5      = histSMA5slice.length
        ? histSMA5slice.reduce((a, b) => a + b, 0) / histSMA5slice.length
        : 0;

    const strongTrend = trendCount >= 3 && histStrength > histSMA5 && vwapDistance > 0.05;
    const strengthText = strongTrend ? 'STRONG' : 'WEAK';

    // ── Duration — Pine exact ─────────────────────────────────────────────────
    // tfSeconds = timeframe.in_seconds()
    // totalSeconds = trendCount * tfSeconds
    const intervalSec   = candles.length > 1
        ? (candles[last].time - candles[last - 1].time)
        : 300;
    const totalSec   = trendCount * intervalSec;
    const mins       = Math.floor(totalSec / 60);
    const hrs        = Math.floor(mins / 60);
    const timeText   = hrs > 0 ? hrs + 'h' : mins + 'm';

    // ── Stoch Status — Pine exact ─────────────────────────────────────────────
    // stochStatus = k > 80 ? "OB" : k < 20 ? "OS" : "MID"
    let stochStatus = 'MID';
    if (k > 80)      stochStatus = 'OB';
    else if (k < 20) stochStatus = 'OS';

    // ── Reversal — Pine exact ─────────────────────────────────────────────────
    // histShrinking = math.abs(hist) < math.abs(hist[1])
    // nearVWAP = math.abs(close - vwapHigh) / close < 0.003  (0.3%)
    // possibleBounce = DOWN and OS and WEAK and histShrinking and nearVWAP
    // possibleDump   = UP  and OB and WEAK and histShrinking and nearVWAP
    const histShrinking = hPrev !== null && Math.abs(h) < Math.abs(hPrev);
    const nearVWAP      = vwapDistance < 0.3;   // Pine: < 0.003 = 0.3%

    let reversal = 'NORMAL';
    if (trend === 'DOWN' && stochStatus === 'OS' && !strongTrend && histShrinking && nearVWAP)
        reversal = 'BOUNCE MODE';
    else if (trend === 'UP' && stochStatus === 'OB' && !strongTrend && histShrinking && nearVWAP)
        reversal = 'DROP MODE';

    // ── MACD Signal ───────────────────────────────────────────────────────────
    const macdSignal = macd > sig ? 'BULL' : 'BEAR';

    return {
        trend,
        trendCount,
        timeText,
        strong: strongTrend,
        strengthText,
        stochStatus,
        stochK: k.toFixed(1),
        reversal,
        macdSignal,
        vwapDiff:      ((cl - vwap) / vwap * 100).toFixed(2),

        // Colors
        trendColor:    trend === 'UP' ? '#4ade80' : trend === 'DOWN' ? '#f87171' : '#fb923c',
        strengthColor: strongTrend ? '#4ade80' : '#fb923c',
        macdColor:     macd > sig ? '#4ade80' : '#f87171',
        stochColor:    stochStatus === 'OB' ? '#f87171' : stochStatus === 'OS' ? '#4ade80' : '#93c5fd',
        reversalColor: reversal === 'BOUNCE MODE' ? '#4ade80' : reversal === 'DROP MODE' ? '#f87171' : '#9ca3af',
        vwapColor:     cl > vwap ? '#4ade80' : '#f87171',
    };
}

// ─── Update SecondEye Panel DOM ───────────────────────────────────────────────
export function updateTrendDashOverlay(candles) {
    const td = computeTrendDashboard(candles);
    if (!td) return;

    const el = (id) => document.getElementById(id);

    // Trend box
    const trendEl = el('se-trend');
    if (trendEl) {
        trendEl.textContent = td.trend;
        trendEl.className   = 'text-2xl font-black tracking-wider ' +
            (td.trend === 'UP' ? 'trend-up' : td.trend === 'DOWN' ? 'trend-down' : 'trend-side');
    }

    const trendBox = el('se-trend-box');
    if (trendBox) trendBox.style.borderColor = td.trendColor + '40';

    setEl('se-strength',   td.strong ? '⚡ STRONG' : '〜 WEAK',                      td.strengthColor);
    setEl('se-duration',   td.trendCount + 'C · ' + td.timeText,                     '#93c5fd');
    setEl('se-vwap-status',(parseFloat(td.vwapDiff) >= 0 ? '+' : '') + td.vwapDiff + '%', td.vwapColor);
    setEl('se-stoch',      td.stochStatus + ' (' + td.stochK + ')',                  td.stochColor);
    setEl('se-macd',       td.macdSignal,                                             td.macdColor);

    // Reversal signal box
    const revEl  = el('se-reversal');
    const revBox = el('se-reversal-box');
    if (revEl) revEl.textContent = td.reversal;
    if (revBox) {
        if (td.reversal === 'BOUNCE MODE') {
            revEl.style.color        = '#4ade80';
            revBox.style.background  = '#0a2010';
            revBox.style.borderColor = '#4ade8060';
        } else if (td.reversal === 'DROP MODE') {
            revEl.style.color        = '#f87171';
            revBox.style.background  = '#20080a';
            revBox.style.borderColor = '#f8717160';
        } else {
            revEl.style.color        = '#6a8aaa';
            revBox.style.background  = '#091020';
            revBox.style.borderColor = '#1a2740';
        }
    }

    // Timestamp
    const now = new Date();
    setEl('se-updated',
        'Updated ' +
        now.getHours().toString().padStart(2, '0') + ':' +
        now.getMinutes().toString().padStart(2, '0') + ':' +
        now.getSeconds().toString().padStart(2, '0'),
        '#2a4060'
    );
}

function setEl(id, text, color) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.style.color = color; }
}
