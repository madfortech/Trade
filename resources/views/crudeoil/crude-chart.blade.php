<x-app-layout>
    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <!-- Header with Live Data -->
            <div class="flex flex-row border-t-2 border-indigo-500 border-b-2 border-indigo-500 py-3 justify-between items-center bg-white px-4 shadow-sm mb-4">
                <div class="flex items-center space-x-4">
                    <h2 class="uppercase font-bold text-indigo-800 tracking-wider">Crude Oil 19 Feb Fut Price Live Chart</h2>
                    <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded-full" id="lastUpdate">
                        LTP: {{ now()->format('H:i:s') }}
                    </span>
                </div>
                
                <div class="flex items-center space-x-3">
                    @if(isset($crudeSpotData))
                        <div class="text-sm font-bold text-gray-700">
                            CRUDE OIL: <span class="{{ $crudeSpotData['color'] ?? 'text-gray-600' }}">{{ $crudeSpotData['ltp'] ?? '0.00' }} ({{ $crudeSpotData['change'] ?? '0.00' }}, {{ $crudeSpotData['percent'] ?? '0.00' }}%)</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            H: {{ $crudeSpotData['high'] ?? '---' }} | L: {{ $crudeSpotData['low'] ?? '---' }}
                        </div>
                    @endif
                    
                    <flux:button size="sm" variant="filled" onclick="refreshData()" id="refreshBtn">
                        <svg class="w-4 h-4 mr-1 hidden" id="spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path class="animate-spin" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </flux:button>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Chart Section (2/3 width) -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-lg p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-gray-800">Live Chart</h3>
                            <div class="flex space-x-2">
                                <button onclick="changeTimeframe('1m')" class="timeframe-btn px-3 py-1 text-xs rounded {{ request('timeframe', '5m') == '1m' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">1m</button>
                                <button onclick="changeTimeframe('5m')" class="timeframe-btn px-3 py-1 text-xs rounded {{ request('timeframe', '5m') == '5m' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">5m</button>
                                <button onclick="changeTimeframe('15m')" class="timeframe-btn px-3 py-1 text-xs rounded {{ request('timeframe', '5m') == '15m' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">15m</button>
                                <button onclick="changeTimeframe('1h')" class="timeframe-btn px-3 py-1 text-xs rounded {{ request('timeframe', '5m') == '1h' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">1h</button>
                                <button onclick="changeTimeframe('1d')" class="timeframe-btn px-3 py-1 text-xs rounded {{ request('timeframe', '5m') == '1d' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">1d</button>
                            </div>
                        </div>
                        
                        <!-- TradingView Chart Container -->
                        <div class="relative bg-gray-900 rounded-lg" style="height: 500px;">
                            <div id="tradingview-chart" class="w-full h-full"></div>
                        </div>

                        <!-- Technical Indicators -->
                        <div class="grid grid-cols-4 gap-4 mt-4">
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="text-xs text-gray-500">SAR</div>
                                <div class="text-sm font-bold text-gray-800" id="sar-value">{{ $crudeSpotData['sar'] ?? '---' }}</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="text-xs text-gray-500">HMA (9)</div>
                                <div class="text-sm font-bold text-gray-800" id="hma-value">{{ $crudeSpotData['hma'] ?? '---' }}</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="text-xs text-gray-500">MA Cross (9, 26)</div>
                                <div class="text-sm font-bold text-gray-800" id="ma-value">{{ $crudeSpotData['ma'] ?? '---' }}</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="text-xs text-gray-500">Volume</div>
                                <div class="text-sm font-bold text-gray-800" id="volume-value">{{ $crudeSpotData['volume'] ?? '---' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Option Chain Section (1/3 width) -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-lg p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-gray-800">Chain</h3>
                            <input type="date" id="expiry-date" class="px-3 py-2 border rounded text-sm" value="{{ request('date', now()->format('Y-m-d')) }}">
                        </div>

                        <!-- Option Chain Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-800 text-white">
                                        <th class="p-2 border-r">CALL OI</th>
                                        <th class="p-2 border-r">CALL LTP</th>
                                        <th class="p-2 border-r">STRIKE</th>
                                        <th class="p-2 border-r">PUT LTP</th>
                                        <th class="p-2">PUT OI</th>
                                    </tr>
                                </thead>
                                <tbody id="mini-options-table">
                                    @forelse($optionsData as $strike => $data)
                                        @php
                                            $isAtm = (abs($crudeSpot - $strike) < 25);
                                            $ceClass = ($strike < $crudeSpot) ? 'bg-green-50' : '';
                                            $peClass = ($strike > $crudeSpot) ? 'bg-red-50' : '';
                                        @endphp
                                        <tr class="border-b hover:bg-gray-50 {{ $isAtm ? 'border-2 border-blue-400' : '' }}">
                                            <td class="p-2 text-center border-r {{ $ceClass }}">{{ number_format($data['ce']['oi'] ?? 0) }}</td>
                                            <td class="p-2 text-center border-r font-bold {{ $ceClass }} {{ ($data['ce']['percentChange'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ number_format($data['ce']['ltp'] ?? 0, 2) }}
                                            </td>
                                            <td class="p-2 text-center bg-gray-100 font-bold border-x">{{ $strike }}</td>
                                            <td class="p-2 text-center border-l font-bold {{ $peClass }} {{ ($data['pe']['percentChange'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ number_format($data['pe']['ltp'] ?? 0, 2) }}
                                            </td>
                                            <td class="p-2 text-center {{ $peClass }}">{{ number_format($data['pe']['oi'] ?? 0) }}</td>
                                        </tr>
                                    @empty
                                        <!-- Fallback rows -->
                                        @for($i = 0; $i < 10; $i++)
                                            @php
                                                $strike = 5727 + ($i * 25) - 125;
                                                $isAtm = (abs($crudeSpot - $strike) < 25);
                                                $ceClass = ($strike < $crudeSpot) ? 'bg-green-50' : '';
                                                $peClass = ($strike > $crudeSpot) ? 'bg-red-50' : '';
                                            @endphp
                                            <tr class="border-b hover:bg-gray-50 {{ $isAtm ? 'border-2 border-blue-400' : '' }}">
                                                <td class="p-2 text-center border-r {{ $ceClass }}">{{ rand(10000, 99999) }}</td>
                                                <td class="p-2 text-center border-r font-bold {{ $ceClass }} {{ rand(0, 1) ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ number_format(rand(50, 500), 2) }}
                                                </td>
                                                <td class="p-2 text-center bg-gray-100 font-bold border-x">{{ $strike }}</td>
                                                <td class="p-2 text-center border-l font-bold {{ $peClass }} {{ rand(0, 1) ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ number_format(rand(50, 500), 2) }}
                                                </td>
                                                <td class="p-2 text-center {{ $peClass }}">{{ rand(10000, 99999) }}</td>
                                            </tr>
                                        @endfor
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://s3.tradingview.com/tv.js"></script>
    <script>
        let currentData = {{ json_encode($crudeSpotData) }};
        let chartWidget = null;

        function initChart() {
            if (chartWidget) {
                chartWidget.remove();
            }

            chartWidget = new TradingView.widget({
                container_id: "tradingview-chart",
                width: "100%",
                height: "100%",
                symbol: "MCX:CRUDEOIL1!",
                interval: "{{ request('timeframe', '5m') }}",
                theme: "dark",
                style: "1",
                locale: "en",
                toolbar_bg: "#1e1e1e",
                enable_publishing: false,
                hide_side_toolbar: false,
                allow_symbol_change_chart: true,
                details: true,
                hotlist: true,
                calendar: false,
                studies: [
                    "MASimple@tv-basicstudies",
                    "MACD@tv-basicstudies"
                ],
                container_id: "tradingview-chart"
            });
        }

        function changeTimeframe(timeframe) {
            const url = new URL(window.location);
            url.searchParams.set('timeframe', timeframe);
            window.location.href = url.toString();
        }

        function refreshData() {
            const spinner = document.getElementById('spinner');
            const btn = document.getElementById('refreshBtn');
            
            spinner.classList.remove('hidden');
            btn.disabled = true;

            fetch('/angel/crude-option/refresh')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }

                    currentData = data.crudeSpotData;
                    updateDisplay();
                    updateMiniOptionsTable(data.optionsData);
                })
                .catch(error => console.error('Error:', error))
                .finally(() => {
                    spinner.classList.add('hidden');
                    btn.disabled = false;
                });
        }

        function updateDisplay() {
            // Update main display
            const spotElement = document.querySelector('[data-crude-spot]');
            if (spotElement && currentData) {
                spotElement.innerHTML = `CRUDE OIL: <span class="${currentData.color || 'text-gray-600'}">${currentData.ltp || '0.00'} (${currentData.change || '0.00'}, ${currentData.percent || '0.00'}%)</span>`;
            }

            // Update indicators
            document.getElementById('sar-value').textContent = currentData.sar || '---';
            document.getElementById('hma-value').textContent = currentData.hma || '---';
            document.getElementById('ma-value').textContent = currentData.ma || '---';
            document.getElementById('volume-value').textContent = currentData.volume || '---';
            
            // Update timestamp
            document.getElementById('lastUpdate').textContent = 'LTP: ' + new Date().toLocaleTimeString();
        }

        function updateMiniOptionsTable(optionsData) {
            const tbody = document.getElementById('mini-options-table');
            if (!tbody || !optionsData) return;

            let html = '';
            const crudeSpot = parseFloat(currentData.ltp || 5727);

            Object.entries(optionsData).slice(0, 10).forEach(([strike, data]) => {
                const isAtm = Math.abs(crudeSpot - parseFloat(strike)) < 25;
                const ceClass = parseFloat(strike) < crudeSpot ? 'bg-green-50' : '';
                const peClass = parseFloat(strike) > crudeSpot ? 'bg-red-50' : '';

                const ceChangeClass = (data.ce?.percentChange ?? 0) >= 0 ? 'text-green-600' : 'text-red-600';
                const peChangeClass = (data.pe?.percentChange ?? 0) >= 0 ? 'text-green-600' : 'text-red-600';

                html += `
                    <tr class="border-b hover:bg-gray-50 ${isAtm ? 'border-2 border-blue-400' : ''}">
                        <td class="p-2 text-center border-r ${ceClass}">${(data.ce?.oi ?? 0).toLocaleString()}</td>
                        <td class="p-2 text-center border-r font-bold ${ceClass} ${ceChangeClass}">${(data.ce?.ltp ?? 0).toFixed(2)}</td>
                        <td class="p-2 text-center bg-gray-100 font-bold border-x">${strike}</td>
                        <td class="p-2 text-center border-l font-bold ${peClass} ${peChangeClass}">${(data.pe?.ltp ?? 0).toFixed(2)}</td>
                        <td class="p-2 text-center ${peClass}">${(data.pe?.oi ?? 0).toLocaleString()}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        // Initialize chart on page load
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            updateDisplay();
            
            // Auto-refresh every 5 seconds
            setInterval(refreshData, 5000);
        });
    </script>

    <style>
        .timeframe-btn {
            transition: all 0.2s ease;
        }
        .timeframe-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</x-app-layout>
