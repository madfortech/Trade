document.addEventListener('DOMContentLoaded', function () {
    const chartEl = document.getElementById('nifty-chart');
    if (!chartEl) return;

    // 1. Initialize Chart
    const chart = LightweightCharts.createChart(chartEl, {
        width: chartEl.clientWidth,
        height: 440,
        layout: { 
            background: { color: '#0d1526' }, 
            textColor: '#94a3b8',
            fontSize: 11,
            fontFamily: 'JetBrains Mono',
        },
        grid: { 
            vertLines: { color: 'rgba(30, 41, 59, 0.1)' }, 
            horzLines: { color: 'rgba(30, 41, 59, 0.1)' } 
        },
        timeScale: {
            timeVisible: true,
            secondsVisible: false,
            borderColor: '#1e293b',
            // FIX: Tick marks ko IST mein convert karke dikhane ke liye
            tickMarkFormatter: (time) => {
                const date = new Date(time * 1000);
                return date.toLocaleTimeString('en-IN', { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    hour12: false,
                    timeZone: 'Asia/Kolkata' // Force IST labels
                });
            },
        },
        localization: {
            locale: 'en-IN',
            priceFormatter: price => price.toFixed(2),
        },
    });

    const candleSeries = chart.addCandlestickSeries({
        upColor: '#22c55e', 
        downColor: '#ef4444',
        borderUpColor: '#22c55e', 
        borderDownColor: '#ef4444',
        wickUpColor: '#22c55e', 
        wickDownColor: '#ef4444',
    });

    window._currentInterval = '5m';
    let lastKnownCandle = null;
    const IST_OFFSET = 19800; // 5 hours 30 minutes in seconds

    // 2. Fetch Historical Data
    window.fetchHistoricalData = async function(interval = '5m') {
        if (window.showChartLoader) window.showChartLoader(interval);
        
        try {
            const response = await fetch(`/angel/nifty/historical?interval=${interval}`);
            const data = await response.json();

            if (data.success && data.candles) {
                const formatted = data.candles.map(c => ({
                    // FIX: Yahan offset add kar rahe hain taaki bars sahi jagah dikhen
                    time: c.time + IST_OFFSET, 
                    open: parseFloat(c.open),
                    high: parseFloat(c.high),
                    low: parseFloat(c.low),
                    close: parseFloat(c.close)
                }));

                candleSeries.setData(formatted);
                chart.timeScale().fitContent();
                
                window._lastCandles = formatted;
                if(formatted.length > 0) {
                    lastKnownCandle = formatted[formatted.length - 1];
                }
            }
        } catch (err) {
            console.error("Chart Load Error:", err);
        } finally {
            if (window.hideChartLoader) window.hideChartLoader(window._lastCandles?.length || 0);
        }
    };

    // 3. Live LTP & Real-time Candle Patching
    window.refreshExactLtp = async function() {
        try {
            const res = await fetch('/angel/nifty/ltp');
            const data = await res.json();
            
            if (data.success) {
                // UI Updates
                document.getElementById('nifty-ltp').textContent = data.ltp.toFixed(2);
                document.getElementById('nifty-high').textContent = data.high.toFixed(2);
                document.getElementById('nifty-low').textContent = data.low.toFixed(2);
                
                // --- LIVE CANDLE LOGIC WITH IST FIX ---
                const nowUTC = Math.floor(Date.now() / 1000);
                const nowIST = nowUTC + IST_OFFSET; // System time to IST
                
                const intervalSec = window._currentInterval === '1h' ? 3600 : (window._currentInterval === '1d' ? 86400 : 300);
                const candleTime = Math.floor(nowIST / intervalSec) * intervalSec;

                let openPrice;
                if (lastKnownCandle && lastKnownCandle.time === candleTime) {
                    openPrice = lastKnownCandle.open;
                } else {
                    openPrice = lastKnownCandle ? lastKnownCandle.close : data.ltp;
                }

                const updatedCandle = {
                    time: candleTime,
                    open: openPrice,
                    high: (lastKnownCandle && lastKnownCandle.time === candleTime) 
                          ? Math.max(lastKnownCandle.high, data.ltp) 
                          : Math.max(openPrice, data.ltp),
                    low: (lastKnownCandle && lastKnownCandle.time === candleTime) 
                          ? Math.min(lastKnownCandle.low, data.ltp) 
                          : Math.min(openPrice, data.ltp),
                    close: data.ltp
                };

                candleSeries.update(updatedCandle);
                lastKnownCandle = updatedCandle;
            }
        } catch (e) {
            console.warn("LTP Syncing...");
        }
    };

    window.changeInterval = function(interval) {
        window._currentInterval = interval;
        lastKnownCandle = null;
        document.querySelectorAll('.nt-interval-btn').forEach(btn => {
            btn.classList.remove('active');
            if(btn.dataset.interval === interval) btn.classList.add('active');
        });
        candleSeries.setData([]);
        window.fetchHistoricalData(interval);
    };

    window.addEventListener('resize', () => {
        chart.applyOptions({ width: chartEl.clientWidth });
    });

    window.fetchHistoricalData('5m');
    setInterval(window.refreshExactLtp, 2000);
});