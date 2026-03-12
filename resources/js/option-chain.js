/**
 * option-chain.js  ← MAIN ENTRY POINT
 * Path: resources/js/option-chain.js
 *
 * Core Logic:
 *   - Market status
 *   - Auto refresh (LTP, OI)
 *   - PCR calculation
 *   - Expiry change
 *   - Keyboard shortcuts
 *
 * Indicators: COMPLETELY REMOVED
 */

'use strict';

import { fetchAndRender, _lastCandles } from './option-chain-chart.js';

// ─── Market Status ────────────────────────────────────────────────────────────
export function isMarketOpen() {
    const ist = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
    const h   = ist.getHours(), m = ist.getMinutes(), d = ist.getDay();
    return d >= 1 && d <= 5
        && (h > 9  || (h === 9  && m >= 15))
        && (h < 15 || (h === 15 && m <= 30));
}

function updateMarketStatus() {
    const badge = document.getElementById('marketStatusBadge');
    if (!badge) return;
    if (isMarketOpen()) {
        badge.textContent = '● Market Open · live';
        badge.className   = 'text-[10px] font-bold px-2 py-1 rounded-full bg-green-100 text-green-700 animate-pulse transition-all';
    } else {
        const ist    = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
        const d      = ist.getDay(), h = ist.getHours();
        const reason = (d === 0 || d === 6)
            ? 'Weekend'
            : (h < 9 || (h === 9 && ist.getMinutes() < 15)) ? 'Pre-Market'
            : 'Market Closed';
        badge.textContent = '○ ' + reason;
        badge.className   = 'text-[10px] font-bold px-2 py-1 rounded-full bg-red-100 text-red-600 transition-all';
    }
}

// ─── PCR Calculation ──────────────────────────────────────────────────────────
function calcPCRLive() {
    let totalCE = 0, totalPE = 0;
    document.querySelectorAll('#chainBody tr[data-ce-oi]').forEach(row => {
        totalCE += parseFloat(row.dataset.ceOi || 0);
        totalPE += parseFloat(row.dataset.peOi || 0);
    });
    const pcr = totalCE > 0 ? (totalPE / totalCE) : 0;

    ['pcrValue', 'footerPcr'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = pcr.toFixed(2);
    });

    const totalCeEl = document.getElementById('totalCeOI');
    const totalPeEl = document.getElementById('totalPeOI');
    if (totalCeEl) totalCeEl.textContent = fmtLakh(totalCE);
    if (totalPeEl) totalPeEl.textContent = fmtLakh(totalPE);

    const pcrSig = document.getElementById('pcrSignal');
    if (pcrSig) pcrSig.textContent =
          pcr >= 1.5 ? '🐂 V.Bullish'
        : pcr >= 1.0 ? '📈 Bullish'
        : pcr >= 0.7 ? '😐 Neutral'
        : pcr >= 0.5 ? '📉 Bearish'
        : '🐻 V.Bearish';
}

function fmtLakh(n) {
    if (n >= 1e7) return (n / 1e7).toFixed(2) + ' Cr';
    if (n >= 1e5) return (n / 1e5).toFixed(2) + ' L';
    return n.toLocaleString('en-IN');
}

// ─── Auto Refresh ─────────────────────────────────────────────────────────────
let autoRefreshOn     = false;
let autoRefreshTimer  = null;
let countdownTimer    = null;
let refreshInProgress = false;

function doRefresh() {
    updateMarketStatus();
    if (refreshInProgress || !isMarketOpen()) return;
    refreshInProgress = true;

    const expiry = document.querySelector('select[onchange]')?.value || '';
    fetch(`/angel/chain-refresh?expiry=${expiry}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.json())
        .then(json => {
            refreshInProgress = false;
            if (!json.success) return;

            // Update spot price
            const spotEl = document.getElementById('niftySpotValue');
            if (spotEl && json.niftySpot)
                spotEl.textContent = parseFloat(json.niftySpot).toLocaleString('en-IN', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                });

            const newSpot = parseFloat(json.niftySpot || 0);
            const newAtm  = json.atm ? parseInt(json.atm) : Math.round(newSpot / 50) * 50;

            // Update rows
            document.querySelectorAll('#chainBody tr[data-strike]').forEach(row => {
                const strike = parseInt(row.dataset.strike);
                const d      = json.data[strike];
                const isAtm  = Math.abs(newSpot - strike) <= 25;

                isAtm
                    ? row.classList.add('atm-row', 'ring-2', 'ring-indigo-400', 'ring-inset')
                    : row.classList.remove('atm-row', 'ring-2', 'ring-indigo-400', 'ring-inset');

                const strikeTd = row.querySelector('[data-strike-td]');
                if (strikeTd) strikeTd.className = 'p-0 text-center border-x font-black '
                    + (isAtm ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800');

                if (!d) return;
                _updateSide(row, d.ce, 'ce');
                _updateSide(row, d.pe, 'pe');
            });

            // Scroll to new ATM
            if (window._lastAtmStrike !== newAtm) {
                window._lastAtmStrike = newAtm;
                const newAtmRow = document.querySelector(`#chainBody tr[data-strike="${newAtm}"]`);
                if (newAtmRow) {
                    newAtmRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    newAtmRow.style.transition = 'background 0.3s';
                    newAtmRow.style.background = 'rgba(99,102,241,0.15)';
                    setTimeout(() => { newAtmRow.style.background = ''; }, 800);
                }
            }

            calcPCRLive();
            const lu = document.getElementById('lastUpdated');
            if (lu) lu.textContent = json.time;
        })
        .catch(() => { refreshInProgress = false; });
}

function _updateSide(row, sideData, side) {
    if (!sideData) return;
    row.dataset[side === 'ce' ? 'ceOi' : 'peOi'] = sideData.oi || 0;

    const ltpEl = row.querySelector(`[data-ltp="${side}"]`);
    if (ltpEl) {
        const nv = parseFloat(sideData.ltp || 0).toFixed(2);
        if (ltpEl.textContent !== nv) {
            ltpEl.textContent = nv;
            ltpEl.classList.remove('ltp-flash');
            void ltpEl.offsetWidth;
            ltpEl.classList.add('ltp-flash');
        }
    }

    const chgEl = row.querySelector(`[data-chg="${side}"]`);
    if (chgEl) {
        const chg     = sideData.percentChange || 0;
        chgEl.textContent = (chg >= 0 ? '▲' : '▼') + Math.abs(chg).toFixed(2) + '%';
        chgEl.className   = 'text-[9px] leading-tight ' + (chg >= 0 ? 'text-green-500' : 'text-red-500');
    }
}

window.toggleAutoRefresh = function () {
    autoRefreshOn = !autoRefreshOn;
    const btn = document.getElementById('autoRefreshBtn');
    const cd  = document.getElementById('refreshCountdown');

    if (autoRefreshOn) {
        btn.textContent = 'ON';
        btn.classList.remove('bg-gray-200', 'text-gray-600');
        btn.classList.add('bg-green-500', 'text-white');
        cd?.classList.remove('hidden');
        countdownTimer   = setInterval(() => { if (cd) cd.textContent = '(1s)'; }, 1000);
        autoRefreshTimer = setInterval(doRefresh, 1000);
        doRefresh();
    } else {
        btn.textContent = 'OFF';
        btn.classList.add('bg-gray-200', 'text-gray-600');
        btn.classList.remove('bg-green-500', 'text-white');
        cd?.classList.add('hidden');
        clearInterval(autoRefreshTimer);
        clearInterval(countdownTimer);
        autoRefreshTimer = countdownTimer = null;
    }
};

// ─── Expiry Change ────────────────────────────────────────────────────────────
window.changeExpiry = function (val) {
    window.location.href = (window.OPTION_CHAIN_ROUTE || '/angel/nifty/option-chain') + '?expiry=' + val;
};

window.openTVChart = function (sym) {
    window.open(`https://in.tradingview.com/chart/?symbol=${sym}`, '_blank');
};

// ─── Page Load ────────────────────────────────────────────────────────────────
function waitForMarketOpen() {
    if (isMarketOpen()) {
        if (!autoRefreshOn) window.toggleAutoRefresh();
        return;
    }
    const ist = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
    const h = ist.getHours(), d = ist.getDay();
    if (d >= 1 && d <= 5 && ((h >= 8 && h < 9) || (h === 9 && ist.getMinutes() < 15)))
        setTimeout(waitForMarketOpen, 5000);
}

window.addEventListener('load', () => {
    calcPCRLive();

    const atm = document.querySelector('.atm-row');
    if (atm) {
        window._lastAtmStrike = parseInt(atm.dataset.strike) || null;
        setTimeout(() => atm.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
    }

    const lu = document.getElementById('lastUpdated');
    if (lu) lu.textContent = new Date().toLocaleTimeString('en-IN', {
        timeZone: 'Asia/Kolkata', hour12: false,
    });

    updateMarketStatus();
    setInterval(updateMarketStatus, 60000);
    waitForMarketOpen();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') window.closeModal?.();
});
