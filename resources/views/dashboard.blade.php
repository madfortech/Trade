<x-app-layout>
    <script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>

    <div class="py-12 bg-gray-900 min-h-screen text-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 p-4 bg-[#161b22] rounded-xl border border-gray-800">
                <h3 class="text-xs text-gray-500 font-bold uppercase">Nifty 50 Live</h3>
                <span id="ltp-display" class="text-3xl font-mono font-bold text-green-400">0.00</span>
            </div>

            <div id="candleChart" style="width: 100%; height: 500px;" class="rounded-lg border border-gray-800 overflow-hidden shadow-2xl"></div>
        </div>
    </div>

    <script>
        let candleSeries;
        let chart;

        function initChart() {
            const chartContainer = document.getElementById('candleChart');
            chart = LightweightCharts.createChart(chartContainer, {
                layout: { background: { color: '#131722' }, textColor: '#d1d4dc' },
                grid: { vertLines: { color: '#2B2B43' }, horzLines: { color: '#2B2B43' } },
                timeScale: { timeVisible: true, borderColor: '#485c7b' },
            });
            candleSeries = chart.addCandlestickSeries({
                upColor: '#26a69a', downColor: '#ef5350', borderVisible: false,
                wickUpColor: '#26a69a', wickDownColor: '#ef5350'
            });
        }

        async function loadChartHistory() {
            try {
                const response = await fetch('/api/nifty/history'); //
                const result = await response.json();
                if (result.status && result.data) {
                    const candleData = result.data.map(item => ({
                        time: Math.floor(new Date(item[0]).getTime() / 1000), //
                        open: parseFloat(item[1]),
                        high: parseFloat(item[2]),
                        low: parseFloat(item[3]),
                        close: parseFloat(item[4])
                    }));
                    candleSeries.setData(candleData.sort((a,b) => a.time - b.time)); //
                }
            } catch (e) { console.log("History Load Error"); }
        }

        async function updateChart() {
            try {
                const response = await fetch('/charts/market/nifty'); //
                const result = await response.json();
                if (result.status && result.data.fetched.length > 0) {
                    const nifty = result.data.fetched[0]; //
                    document.getElementById('ltp-display').innerText = nifty.ltp.toLocaleString('en-IN');
                    candleSeries.update({
                        time: Math.floor(Date.now() / 1000),
                        open: parseFloat(nifty.open), high: parseFloat(nifty.high),
                        low: parseFloat(nifty.low), close: parseFloat(nifty.ltp)
                    });
                }
            } catch (e) { console.log("Live Update Error"); }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            initChart();
            await loadChartHistory();
            setInterval(updateChart, 2000); //
        });
    </script>
</x-app-layout>