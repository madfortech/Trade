'use strict';
let refreshTimer = null;
let tvChart = null;
let candleSeries = null;

function isMarketOpen() {

    const now = new Date(
        new Date().toLocaleString(
            'en-US',
            { timeZone: 'Asia/Kolkata' }
        )
    );

    const day = now.getDay();

    const h = now.getHours();

    const m = now.getMinutes();

    return (
        day >= 1 &&
        day <= 5 &&
        (
            h > 9 ||
            (h === 9 && m >= 15)
        ) &&
        (
            h < 15 ||
            (h === 15 && m <= 30)
        )
    );
}

window.currentStock = null;
window.loadChart = async function (stock, isRefresh = false) {

    if (isRefresh && !isMarketOpen()) {
        return;
    }

    window.currentStock = stock;

    
try {

    console.log('STOCK OBJECT:', stock);

    const results = document.getElementById('results');

    // if (!isRefresh) {
    //     results.innerHTML = 'Loading candles...';
    // }

    if (!isRefresh) {
    results.innerHTML = `
        <div class="flex items-center gap-2 text-indigo-600">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10"
                    stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span>
                Loading ${stock.symbol}
                (${stock.interval || '5m'}) candles...
    </span>
        </div>
    `;
    }

    const interval = stock.interval || '5m';

    const url =
        `/angel/candle-data?token=${stock.token}&exchange=${stock.exchange}&interval=${interval}`;

    console.log('REQUEST URL:', url);

    console.log('isRefresh=', isRefresh);

    const res = await fetch(url);

    const json = await res.json();

    console.log('API RESPONSE:', json);

    // if (!json.success) {

    //     results.innerHTML = `
    //         <div class="text-red-500">
    //             ${json.message}
    //         </div>
    //     `;

    //     return;
    // }

    if (!json.success) {

        console.warn('Refresh failed:', json.message);

        return;
    }


    window._stockCandles = json.data || [];

    if (!isRefresh) {
        results.innerHTML = `
        <div>
            <strong>${stock.symbol}</strong>
            <br>
            ${stock.interval || '5m'} ·
            ${json.data.length} candles loaded
        </div>
        `;
    }

    renderTradingView(json.data);
    clearInterval(refreshTimer);

    refreshTimer = setInterval(() => {

        if (!isMarketOpen()) {

            console.log('Market closed');

            return;
        }

        if (window.currentStock) {

            console.log('Auto refresh...');

            loadChart(
                window.currentStock,
                true
            );
        }

    }, 30000);

} catch (e) {

    console.error('CHART ERROR:', e);
}


};

function renderTradingView(candles)
{
const container = document.getElementById('tv-chart');


if (!container) {
    console.error('tv-chart container not found');
    return;
}

if (tvChart) {
    tvChart.remove();
    tvChart = null;
}

container.innerHTML = '';

try {

    tvChart = LightweightCharts.createChart(container, {
        height: 600,
        width: container.clientWidth,
    });

    candleSeries = tvChart.addCandlestickSeries();

    let data = candles
        .filter(c =>
            c &&
            c.length >= 5 &&
            c[0]
        )
        .map(c => ({
            time: Math.floor(
                new Date(c[0]).getTime() / 1000
            ),
            open: Number(c[1]),
            high: Number(c[2]),
            low: Number(c[3]),
            close: Number(c[4]),
        }))
        .filter(c =>
            !isNaN(c.time) &&
            !isNaN(c.open) &&
            !isNaN(c.high) &&
            !isNaN(c.low) &&
            !isNaN(c.close)
        );

    // Sort by time
    data.sort((a, b) => a.time - b.time);

    // Remove duplicate timestamps
    const seen = new Set();

    data = data.filter(item => {

        if (seen.has(item.time)) {
            return false;
        }

        seen.add(item.time);

        return true;
    });

    console.log('Total candles:', candles.length);
    console.log('Clean candles:', data.length);
    console.log('First candle:', data[0]);
    console.log('Last candle:', data[data.length - 1]);

    window._lastCandles = data;
    window.currentCandles = data;
    window.candles = data;

    console.log('GLOBAL CANDLES:', window._lastCandles.length);

    candleSeries.setData(data);

    tvChart.timeScale().fitContent();

    const aiPanel = document.getElementById('aiPanel');

    if (aiPanel) {
        aiPanel.classList.remove('hidden');
    }

    console.log('Chart rendered');

} catch (e) {

    console.error('CHART ERROR', e);
}


}
