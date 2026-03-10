/**
 * chart.js
 * Nifty Live Chart — Clean version (no indicators)
 * Place in: resources/js/chart.js
 */

document.addEventListener('DOMContentLoaded', function () {

    const chartEl = document.getElementById('nifty-chart');
    if (!chartEl) return;

    // ── Chart Init ────────────────────────────────────────────────────────────
    const chart = LightweightCharts.createChart(chartEl, {
        width:  chartEl.clientWidth,
        height: chartEl.clientHeight || 440,
        layout: { background: { color: '#080e1c' }, textColor: '#5a7a9a' },
        grid:   { vertLines: { color: '#0f1e30' }, horzLines: { color: '#0f1e30' } },
        crosshair:       { mode: LightweightCharts.CrosshairMode.Normal },
        rightPriceScale: { borderColor: '#1a2740' },
        timeScale: {
            borderColor:    '#1a2740',
            timeVisible:    true,
            secondsVisible: false,
        },
    });

    const candleSeries = chart.addCandlestickSeries({
        upColor:       '#4ade80', downColor:       '#f87171',
        borderUpColor: '#4ade80', borderDownColor: '#f87171',
        wickUpColor:   '#4ade80', wickDownColor:   '#f87171',
    });

    const volumeSeries = chart.addHistogramSeries({
        priceFormat:  { type: 'volume' },
        priceScaleId: 'volume',
        scaleMargins: { top: 0.92, bottom: 0 },
    });

    window.addEventListener('resize', () => {
        chart.applyOptions({ width: chartEl.clientWidth });
    });

    // ── Fetch Historical Data ─────────────────────────────────────────────────
    window._currentInterval = '5m';

    async function fetchHistoricalData(interval = '5m') {
        window._currentInterval = interval;

        if (window.showChartLoader) window.showChartLoader(interval);

        try {
            const res  = await fetch(`/angel/nifty/historical?interval=${interval}`);
            const data = await res.json();

            if (!data.candles?.length) {
                if (window.hideChartLoader) window.hideChartLoader(0);
                return;
            }

            const sorted = [...data.candles].sort(
                (a, b) => new Date(a[0]) - new Date(b[0])
            );

            // ── Store candles globally for AI Analysis ────────────────────────
            window._lastCandles = sorted;

            // ── Candlestick data ──────────────────────────────────────────────
            candleSeries.setData(sorted.map(c => ({
                time:  Math.floor(new Date(c[0]).getTime() / 1000) + 19800,
                open:  c[1],
                high:  c[2],
                low:   c[3],
                close: c[4],
            })));

            // ── Volume data ───────────────────────────────────────────────────
            volumeSeries.setData(sorted.map(c => ({
                time:  Math.floor(new Date(c[0]).getTime() / 1000) + 19800,
                value: c[5] || 0,
                color: c[4] >= c[1] ? '#4ade8025' : '#f8717125',
            })));

            chart.timeScale().fitContent();

            // ── Stats bar update ──────────────────────────────────────────────
            const last   = sorted[sorted.length - 1];
            const first  = sorted[0];
            const change = ((last[4] - first[1]) / first[1] * 100).toFixed(2);
            const allVol = sorted.reduce((a, c) => a + (c[5] || 0), 0);

            const ltpEl    = document.getElementById('nifty-ltp');
            const highEl   = document.getElementById('nifty-high');
            const lowEl    = document.getElementById('nifty-low');
            const volEl    = document.getElementById('nifty-vol');
            const changeEl = document.getElementById('nifty-change');

            if (ltpEl)    ltpEl.textContent    = last[4].toFixed(2);
            if (highEl)   highEl.textContent   = Math.max(...sorted.map(c => c[2])).toFixed(2);
            if (lowEl)    lowEl.textContent    = Math.min(...sorted.map(c => c[3])).toFixed(2);
            if (volEl)    volEl.textContent    = (allVol / 1e6).toFixed(1) + 'M';
            if (changeEl) {
                changeEl.textContent = `${change >= 0 ? '+' : ''}${change}%`;
                changeEl.style.color = change >= 0 ? '#16a34a' : '#dc2626';
            }

            // ── Hide loader ───────────────────────────────────────────────────
            if (window.hideChartLoader) window.hideChartLoader(sorted.length);

        } catch (err) {
            console.error('Chart fetch error:', err);
            if (window.hideChartLoader) window.hideChartLoader(0);
        }
    }

    // ── Change Interval (called from blade buttons) ───────────────────────────
    window.changeInterval = function (interval) {
        // Update button styles
        document.querySelectorAll('.nt-interval-btn, .interval-btn').forEach(btn => {
            const isActive = btn.dataset.interval === interval;
            btn.classList.toggle('active', isActive);
        });
        fetchHistoricalData(interval);
    };

    // ── Trendline Tool ────────────────────────────────────────────────────────
    let activeTool  = null;
    let clickPoints = [];
    let trendlines  = [];
    let pendingLine = null;

    window.setTool = function (tool) {
        activeTool = (activeTool === tool) ? null : tool;

        const btn = document.getElementById('btn-trendline');
        if (activeTool) {
            btn?.classList.add('active-tool');
            setStatus('📍 Pehla point click karo...');
            chartEl.style.cursor = 'crosshair';
        } else {
            btn?.classList.remove('active-tool');
            setStatus('Tip: Trend Line → 2 points click karo chart pe');
            chartEl.style.cursor = 'default';
        }
    };

    window.clearTrendlines = function () {
        trendlines.forEach(l => chart.removeSeries(l));
        trendlines  = [];
        clickPoints = [];
        if (pendingLine) { chart.removeSeries(pendingLine); pendingLine = null; }
        setStatus('✅ Saari lines clear ho gayi!');
    };

    function setStatus(msg) {
        const el = document.getElementById('chart-status');
        if (el) el.textContent = msg;
    }

    function extendTrendline(p1, p2) {
        const timeDiff = p2.time - p1.time;
        const slope    = (p2.value - p1.value) / timeDiff;
        const ext      = timeDiff * 0.5;
        return [
            { time: Math.round(p1.time - ext),   value: p1.value - slope * ext },
            { time: p1.time,                      value: p1.value },
            { time: p2.time,                      value: p2.value },
            { time: Math.round(p2.time + ext),    value: p2.value + slope * ext },
        ];
    }

    chart.subscribeClick(function (param) {
        if (activeTool !== 'trendline' || !param.time) return;
        const price = candleSeries.coordinateToPrice(param.point.y);
        if (!price) return;

        clickPoints.push({ time: param.time, value: price });

        if (clickPoints.length === 1) {
            setStatus('📍 Doosra point click karo...');
            pendingLine = chart.addLineSeries({
                color: '#3b82f6', lineWidth: 1,
                lineStyle: LightweightCharts.LineStyle.Dashed,
                priceLineVisible: false, lastValueVisible: false,
            });
            pendingLine.setData([clickPoints[0], clickPoints[0]]);

        } else if (clickPoints.length === 2) {
            if (pendingLine) { chart.removeSeries(pendingLine); pendingLine = null; }
            const pts  = [...clickPoints].sort((a, b) => a.time - b.time);
            const line = chart.addLineSeries({
                color: '#3b82f6', lineWidth: 2,
                lineStyle: LightweightCharts.LineStyle.Solid,
                priceLineVisible: false, lastValueVisible: false,
            });
            line.setData(extendTrendline(pts[0], pts[1]));
            trendlines.push(line);
            clickPoints = [];
            activeTool  = null;
            document.getElementById('btn-trendline')?.classList.remove('active-tool');
            chartEl.style.cursor = 'default';
            setStatus(`✅ Trendline bani! Total: ${trendlines.length} | Clear ke liye 🗑 click karo`);
        }
    });

    chart.subscribeCrosshairMove(function (param) {
        if (activeTool !== 'trendline' || clickPoints.length !== 1) return;
        if (!param.time || !param.point || !pendingLine) return;
        const price = candleSeries.coordinateToPrice(param.point.y);
        if (!price) return;
        pendingLine.setData(
            [clickPoints[0], { time: param.time, value: price }]
                .sort((a, b) => a.time - b.time)
        );
    });

    // ── Initial load ──────────────────────────────────────────────────────────
    fetchHistoricalData('5m');

});
