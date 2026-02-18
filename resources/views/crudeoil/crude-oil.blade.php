<x-app-layout>
    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            <!-- ================= HEADER ================= -->
            <div class="flex flex-row border-t-2 border-indigo-500 border-b-2 border-indigo-500 py-3 justify-between items-center bg-white px-4 shadow-sm">
                <div class="flex items-center space-x-4">
                    <h2 class="uppercase font-bold text-indigo-800 tracking-wider">
                        Crude Oil Option Chain
                    </h2>
                    
                    <!-- Expiry Date Selector -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Expiry:</label>
                        <select id="expirySelect" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="changeExpiry()">
                            <option value="">Select Expiry</option>
                            <option value="current" {{ request('expiry') == 'current' ? 'selected' : '' }}>Current Month</option>
                            <option value="next" {{ request('expiry') == 'next' ? 'selected' : '' }}>Next Month</option>
                            <option value="far" {{ request('expiry') == 'far' ? 'selected' : '' }}>Far Month</option>
                        </select>
                        
                        <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded-full" id="lastUpdate">
                            LTP: {{ now()->format('H:i:s') }}
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="text-sm font-bold text-gray-700" id="crude-spot-area">
                        CRUDE OIL:
                        <span id="spotPriceDisplay"
                              class="{{ $crudeSpotData['color'] ?? 'text-gray-600' }}">
                            {{ $crudeSpotData['ltp'] ?? '0.00' }}
                            ({{ $crudeSpotData['change'] ?? '0.00' }},
                             {{ $crudeSpotData['percent'] ?? '0.00' }}%)
                        </span>
                    </div>
                    
                    <div class="text-xs text-gray-500" id="high-low-display">
                        H: {{ $crudeSpotData['high'] ?? '---' }}
                        |
                        L: {{ $crudeSpotData['low'] ?? '---' }}
                    </div>
                    
                    <button class="px-3 py-1 bg-indigo-600 text-white text-xs rounded shadow hover:bg-indigo-700 flex items-center"
                            onclick="refreshData()" id="refreshBtn">

                        <span id="spinner" class="hidden animate-spin mr-2">↻</span>
                        <span id="btnText">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- ================= OPTION TABLE ================= -->
            <div class="mt-4 overflow-x-auto shadow-lg rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-[11px] border-collapse uppercase tracking-tighter">

                    <thead>
                        <tr class="bg-gray-800 text-white">
                            <th colspan="5" class="py-2 border-r border-gray-600">CALLS (CE)</th>
                            <th class="bg-gray-900">STRIKE</th>
                            <th colspan="5" class="py-2 border-l border-gray-600">PUTS (PE)</th>
                        </tr>
                        <tr class="bg-gray-100 text-gray-600 border-b">
                            <th class="p-2 border-r">OI</th>
                            <th class="p-2 border-r">IV</th>
                            <th class="p-2 border-r">Delta</th>
                            <th class="p-2 border-r">Chg%</th>
                            <th class="p-2 border-r">LTP</th>
                            <th class="p-2 bg-gray-200 text-black font-bold">PRICE</th>
                            <th class="p-2 border-l">LTP</th>
                            <th class="p-2 border-l">Chg%</th>
                            <th class="p-2 border-l">Delta</th>
                            <th class="p-2 border-l">IV</th>
                            <th class="p-2">OI</th>
                        </tr>
                    </thead>

                    <tbody id="optionsTableBody">
                        @forelse($optionsData as $strike => $data)
                            @php
                                $isAtm = (isset($crudeSpot) && abs($crudeSpot - $strike) < 25);
                                $ceItm = (isset($crudeSpot) && $strike < $crudeSpot) ? 'bg-yellow-50' : '';
                                $peItm = (isset($crudeSpot) && $strike > $crudeSpot) ? 'bg-yellow-50' : '';
                            @endphp
                            <tr class="border-b hover:bg-blue-50 {{ $isAtm ? 'border-y-2 border-indigo-400' : '' }}">
                                <td class="p-2 text-center border-r {{ $ceItm }}">
                                    {{ number_format($data['ce']['oi'] ?? 0) }}
                                </td>
                                <td class="p-2 text-center border-r {{ $ceItm }} text-gray-500">
                                    {{ $data['ce']['iv'] ?? '-' }}
                                </td>
                                <td class="p-2 text-center border-r {{ $ceItm }} text-purple-600">
                                    {{ $data['ce']['delta'] ?? '-' }}
                                </td>
                                <td class="p-2 text-center border-r {{ $ceItm }} {{ ($data['ce']['percentChange'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($data['ce']['percentChange'] ?? 0, 2) }}%
                                </td>
                                <td class="p-2 text-right border-r font-bold {{ $ceItm }} text-blue-700">
                                    {{ number_format($data['ce']['ltp'] ?? 0, 2) }}
                                </td>
                                <td class="p-2 text-center bg-gray-100 font-black text-sm border-x">
                                    {{ $strike }}
                                </td>
                                <td class="p-2 text-left border-l font-bold {{ $peItm }} text-blue-700">
                                    {{ number_format($data['pe']['ltp'] ?? 0, 2) }}
                                </td>
                                <td class="p-2 text-center border-l {{ $peItm }} {{ ($data['pe']['percentChange'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($data['pe']['percentChange'] ?? 0, 2) }}%
                                </td>
                                <td class="p-2 text-center border-l {{ $peItm }} text-purple-600">
                                    {{ $data['pe']['delta'] ?? '-' }}
                                </td>
                                <td class="p-2 text-center border-l {{ $peItm }} text-gray-500">
                                    {{ $data['pe']['iv'] ?? '-' }}
                                </td>
                                <td class="p-2 text-center {{ $peItm }}">
                                    {{ number_format($data['pe']['oi'] ?? 0) }}
                                </td>
                            </tr>
                        @empty
                            @php
                                $fallbackSpot = $crudeSpot ?? 5727.00;
                                $baseStrike = round($fallbackSpot / 25) * 25;
                                $fallbackStrikes = [];

                                for ($i = -8; $i <= 8; $i++) {
                                    $strike = $baseStrike + ($i * 25);
                                    if ($strike >= 5000 && $strike <= 7000) {
                                        $fallbackStrikes[] = $strike;
                                    }
                                }
                            @endphp
                            @foreach($fallbackStrikes as $strike)
                                @php
                                    $isAtm = (isset($crudeSpot) && abs($crudeSpot - $strike) < 25);
                                    $ceItm = (isset($crudeSpot) && $strike < $crudeSpot) ? 'bg-yellow-50' : '';
                                    $peItm = (isset($crudeSpot) && $strike > $crudeSpot) ? 'bg-yellow-50' : '';
                                @endphp
                                <tr class="border-b hover:bg-blue-50 {{ $isAtm ? 'border-y-2 border-indigo-400' : '' }}">
                                    <td class="p-2 text-center border-r {{ $ceItm }}">
                                        {{ rand(10000, 99999) }}
                                    </td>
                                    <td class="p-2 text-center border-r {{ $ceItm }} text-gray-500">
                                        {{ rand(20, 45) }}%
                                    </td>
                                    <td class="p-2 text-center border-r {{ $ceItm }} text-purple-600">
                                        {{ number_format(rand(10, 90)/100, 2) }}
                                    </td>
                                    <td class="p-2 text-center border-r {{ $ceItm }} {{ rand(0, 1) ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format(rand(-15, 15), 2) }}%
                                    </td>
                                    <td class="p-2 text-right border-r font-bold {{ $ceItm }} text-blue-700">
                                        {{ number_format(rand(50, 500) + rand(0, 100)/100, 2) }}
                                    </td>
                                    <td class="p-2 text-center bg-gray-100 font-black text-sm border-x">
                                        {{ $strike }}
                                    </td>
                                    <td class="p-2 text-left border-l font-bold {{ $peItm }} text-blue-700">
                                        {{ number_format(rand(50, 500) + rand(0, 100)/100, 2) }}
                                    </td>
                                    <td class="p-2 text-center border-l {{ $peItm }} {{ rand(0, 1) ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format(rand(-15, 15), 2) }}%
                                    </td>
                                    <td class="p-2 text-center border-l {{ $peItm }} text-purple-600">
                                        {{ number_format(rand(10, 90)/100, 2) }}
                                    </td>
                                    <td class="p-2 text-center border-l {{ $peItm }} text-gray-500">
                                        {{ rand(20, 45) }}%
                                    </td>
                                    <td class="p-2 text-center {{ $peItm }}">
                                        {{ rand(10000, 99999) }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>


    <!-- ================= JAVASCRIPT ================= -->
    <script>

        async function refreshData() {

            const spinner = document.getElementById('spinner');
            const btnText = document.getElementById('btnText');
            const btn = document.getElementById('refreshBtn');
            
            spinner.classList.remove('hidden');
            btnText.innerText = 'Updating...';
            btn.disabled = true;

            try {
                // Get selected expiry
                const expirySelect = document.getElementById('expirySelect');
                const selectedExpiry = expirySelect ? expirySelect.value : 'current';
                
                const response = await fetch(`/angel/crude-option/refresh?expiry=${selectedExpiry}`);
                const data = await response.json();

                if (!data || data.error) {
                    console.error("Server Error:", data?.error);
                    return;
                }

                /* ===== Spot Update ===== */

                if (data.crudeSpotData) {

                    const spotDisplay = document.getElementById('spotPriceDisplay');

                    spotDisplay.className = data.crudeSpotData.color ?? 'text-gray-600';

                    const ltp = Number(data.crudeSpotData.ltp ?? 0).toFixed(2);
                    const change = Number(data.crudeSpotData.change ?? 0).toFixed(2);
                    const percent = Number(data.crudeSpotData.percent ?? 0).toFixed(2);

                    spotDisplay.innerText =
                        `${ltp} (${change}, ${percent}%)`;

                    document.getElementById('high-low-display').innerText =
                        `H: ${data.crudeSpotData.high ?? '---'} | L: ${data.crudeSpotData.low ?? '---'}`;
                }

                /* ===== Table Update ===== */

                if (!data.optionsData) return;

                const tbody = document.getElementById('optionsTableBody');
                let newHtml = '';
                const spot = Number(data.crudeSpot ?? 0);

                Object.entries(data.optionsData).forEach(([strike, opt]) => {

                    const s = Number(strike);
                    const isAtm = Math.abs(spot - s) < 25;
                    const ceItm = (s < spot) ? 'bg-yellow-50' : '';
                    const peItm = (s > spot) ? 'bg-yellow-50' : '';

                    const ce = opt.ce ?? {};
                    const pe = opt.pe ?? {};

                    newHtml += `
                    <tr class="border-b hover:bg-blue-50 ${isAtm ? 'border-y-2 border-indigo-400' : ''}">
                        <td class="p-2 text-center border-r ${ceItm}">${Number(ce.oi ?? 0).toLocaleString()}</td>
                        <td class="p-2 text-center border-r ${ceItm} text-gray-500">${ce.iv ?? '-'}</td>
                        <td class="p-2 text-center border-r ${ceItm} text-purple-600">${ce.delta ?? '-'}</td>
                        <td class="p-2 text-center border-r ${ceItm} ${(ce.percentChange ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'}">
                            ${Number(ce.percentChange ?? 0).toFixed(2)}%
                        </td>
                        <td class="p-2 text-right border-r font-bold ${ceItm} text-blue-700">
                            ${Number(ce.ltp ?? 0).toFixed(2)}
                        </td>

                        <td class="p-2 text-center bg-gray-100 font-black text-sm border-x">
                            ${strike}
                        </td>

                        <td class="p-2 text-left border-l font-bold ${peItm} text-blue-700">
                            ${Number(pe.ltp ?? 0).toFixed(2)}
                        </td>
                        <td class="p-2 text-center border-l ${peItm} ${(pe.percentChange ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'}">
                            ${Number(pe.percentChange ?? 0).toFixed(2)}%
                        </td>
                        <td class="p-2 text-center border-l ${peItm} text-purple-600">${pe.delta ?? '-'}</td>
                        <td class="p-2 text-center border-l ${peItm} text-gray-500">${pe.iv ?? '-'}</td>
                        <td class="p-2 text-center ${peItm}">
                            ${Number(pe.oi ?? 0).toLocaleString()}
                        </td>
                    </tr>`;
                });

                tbody.innerHTML = newHtml;

                document.getElementById('lastUpdate').innerText =
                    'LTP: ' + new Date().toLocaleTimeString();

            } catch (err) {
                console.error("JS Error:", err);
            } finally {
                spinner.classList.add('hidden');
                btnText.innerText = 'Refresh';
                btn.disabled = false;
            }
        }

        function changeExpiry() {
            const expirySelect = document.getElementById('expirySelect');
            const selectedExpiry = expirySelect ? expirySelect.value : 'current';
            
            // Reload page with selected expiry
            window.location.href = `/angel/crude-option?expiry=${selectedExpiry}`;
        }

        setInterval(refreshData, 5000);

    </script>

</x-app-layout>
