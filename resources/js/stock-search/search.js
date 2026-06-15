'use strict';

function resetAiPanel() {

    document.getElementById('aiWaiting')
        ?.style.setProperty('display', 'flex');

    document.getElementById('aiSkeleton')
        ?.classList.add('hidden');

    document.getElementById('aiVerdictArea')
        ?.style.setProperty('display', 'none');
}

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
            <div class="flex justify-between items-center">
                <div>
                    <strong>${stock.symbol}</strong>
                    <br>
                    ${stock.exchange}
                </div>

                <div class="flex gap-1">
                    <button class="tf-btn px-2 py-1 text-xs bg-blue-600 text-white rounded" data-tf="1d">1D</button>
                    <button class="tf-btn px-2 py-1 text-xs bg-blue-600 text-white rounded" data-tf="1h">1H</button>
                    <button class="tf-btn px-2 py-1 text-xs bg-blue-600 text-white rounded" data-tf="15m">15M</button>
                    <button class="tf-btn px-2 py-1 text-xs bg-blue-600 text-white rounded" data-tf="5m">5M</button>
                </div>
            </div>
            `;


            item.querySelectorAll('.tf-btn').forEach(btn => {

                btn.addEventListener('click', (e) => {

                    e.stopPropagation();

                    resetAiPanel();

                    loadChart({
                        ...stock,
                        interval: btn.dataset.tf
                    });
                });

            });

            item.addEventListener('click', () => {

                resetAiPanel();

                loadChart({
                    ...stock,
                    interval: '5m'
                });

            });
                    
            results.appendChild(item);
            console.log('Appended');
        });

    } catch (e) {

        console.error('Search Error:', e);
    }
};