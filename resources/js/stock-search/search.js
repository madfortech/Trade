'use strict';

window.searchStock = async function () {

    const symbol = document
        .getElementById('symbol')
        ?.value
        ?.trim();

    if (!symbol) {
        alert('Enter symbol');
        return;
    }

    try {

        const res = await fetch(
            `/angel/search-stock?q=${encodeURIComponent(symbol)}`
        );

        const json = await res.json();

        console.log('Response:', json);
        console.log('JSON String:', JSON.stringify(json, null, 2));

        const results =
            document.getElementById('results');

        console.log('Results Element:', results);

        results.innerHTML = '';

        const stocks = json?.data ?? [];

        if (!Array.isArray(stocks)) {
            console.error(
                'Expected data to be an array but got:',
                stocks
            );
            return;
        }

        stocks.forEach(stock => {

            console.log('Creating item:', stock);

            const item =
                document.createElement('div');

            item.className =
                'p-2 border rounded mb-2 cursor-pointer';

            item.innerHTML = `
                <strong>${stock.symbol}</strong>
                <br>
                ${stock.exchange}
            `;

            item.onclick = () =>
                loadChart(stock);

            results.appendChild(item);
            console.log('Appended');
        });

    } catch (e) {

        console.error('Search Error:', e);
    }
};