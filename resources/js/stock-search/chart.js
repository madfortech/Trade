'use strict';

window.loadChart = async function (
    stock
) {

    try {

        const results =
            document.getElementById(
                'results'
            );

        results.innerHTML = `
            Loading 5000 candles...
        `;

        const res = await fetch(
            `/angel/candle-data?token=${stock.token}&exchange=${stock.exchange}`
        );

        const json =
            await res.json();

        window._stockCandles = json.data || [];

        results.innerHTML = `
            <div>
                <strong>
                    ${stock.symbol}
                </strong>

                <br>

                ${json.data.length}
                candles loaded
            </div>
        `;

        renderTradingView(
            json.data || []
        );

    } catch (e) {

        console.error(e);
    }
};

function renderTradingView(candles)
{
    const container =
        document.getElementById(
            'tv-chart'
        );

    container.innerHTML = '';

    const chart =
        LightweightCharts.createChart(
            container,
            {
                height: 600,
                width: container.clientWidth,
            }
        );

    const candleSeries =
        chart.addCandlestickSeries();

    const data = candles.map(c => ({
        time: Math.floor(
            new Date(c[0]).getTime() / 1000
        ),
        open: Number(c[1]),
        high: Number(c[2]),
        low: Number(c[3]),
        close: Number(c[4]),
    }));

    candleSeries.setData(data);

    chart.timeScale().fitContent();
}
