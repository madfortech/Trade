<x-app-layout>
    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-row border-t-2 border-indigo-500 border-b-2 border-indigo-500 py-3 justify-between items-center bg-white px-4 shadow-sm">
                <div class="flex items-center space-x-4">
                    <h2 class="uppercase font-bold text-indigo-800 tracking-wider">Nifty Option Chain</h2>
                    <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded-full" id="lastUpdate">
                        Refreshed: {{ now()->format('H:i:s') }}
                    </span>
                    @if(isset($expiryDate))
                        <span class="text-xs font-bold px-2 py-1 bg-purple-100 text-purple-800 rounded-full">
                            Expiry: {{ $expiryDate }}
                        </span>
                    @endif
                    @if(isset($marketStatus))
                        <span class="text-xs font-bold px-2 py-1 {{ $marketStatus['is_open'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded-full">
                            {{ $marketStatus['status'] }}
                        </span>
                        <span class="text-xs font-medium px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                            {{ $marketStatus['message'] }}
                        </span>
                    @endif
                </div>
                
                <div class="flex items-center space-x-3">
                    @if(isset($niftySpot))
                        <div class="text-sm font-bold text-gray-700">
                            NIFTY 50 SPOT: <span class="text-indigo-600">{{ number_format($niftySpot, 2) }}</span>
                        </div>
                    @endif
                    
                    <flux:button size="sm" variant="filled" onclick="refreshData()" id="refreshBtn" 
                        class="{{ $marketStatus['is_open'] ?? false ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700' }}">
                        <svg class="w-4 h-4 mr-1 hidden" id="spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path class="animate-spin" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        {{ $marketStatus['is_open'] ?? false ? 'Live Refresh' : 'Refresh' }}
                    </flux:button>
                </div>
            </div>
            
            @if(isset($marketStatus) && !$marketStatus['is_open'])
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Market Closed:</strong> Current time: {{ $marketStatus['current_time'] }} | 
                                Next market open: {{ $marketStatus['next_open'] }}
                            </p>
                            <p class="text-xs text-yellow-600 mt-1">
                                Showing cached data. Live prices will be available during market hours (9:15 AM - 3:30 PM).
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-4 overflow-x-auto shadow-lg rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-[10px] border-collapse uppercase tracking-tighter">
                    <thead>
                        <tr class="bg-gray-800 text-white">
                            <th colspan="9" class="py-2 border-r border-gray-600">CALLS (CE)</th>
                            <th class="bg-gray-900">STRIKE</th>
                            <th colspan="9" class="py-2 border-l border-gray-600">PUTS (PE)</th>
                        </tr>
                        <tr class="bg-gray-100 text-gray-600 border-b">
                            <th class="p-1 border-r">OI</th>
                            <th class="p-1 border-r">Vol</th>
                            <th class="p-1 border-r">IV</th>
                            <th class="p-1 border-r">Delta</th>
                            <th class="p-1 border-r">Gamma</th>
                            <th class="p-1 border-r">Theta</th>
                            <th class="p-1 border-r">Vega</th>
                            <th class="p-1 border-r">Chg%</th>
                            <th class="p-1 border-r">LTP</th>
                            
                            <th class="p-1 bg-gray-200 text-black font-bold">PRICE</th>
                            
                            <th class="p-1 border-l">LTP</th>
                            <th class="p-1 border-l">Chg%</th>
                            <th class="p-1 border-l">Vega</th>
                            <th class="p-1 border-l">Theta</th>
                            <th class="p-1 border-l">Gamma</th>
                            <th class="p-1 border-l">Delta</th>
                            <th class="p-1 border-l">IV</th>
                            <th class="p-1 border-l">Vol</th>
                            <th class="p-1">OI</th>
                        </tr>
                    </thead>
                    <tbody id="optionsTableBody">
                        @forelse($optionsData as $strike => $data)
                            @php
                                // ATM Highlight (Near 25 points)
                                $isAtm = (abs($niftySpot - $strike) <= 25);
                                // ITM Coloring logic
                                $ceItm = ($strike < $niftySpot) ? 'bg-yellow-50/50' : '';
                                $peItm = ($strike > $niftySpot) ? 'bg-yellow-50/50' : '';
                            @endphp
                            <tr class="border-b hover:bg-blue-50 transition-colors {{ $isAtm ? 'bg-indigo-50/30' : '' }}">
                                <td class="p-1 text-center border-r {{ $ceItm }}">{{ number_format($data['ce']['oi'] ?? 0) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} text-blue-500">{{ number_format($data['ce']['volume'] ?? 0, 0) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} text-gray-500">{{ number_format($data['ce']['iv'] ?? 0, 2) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} text-purple-600 font-medium">{{ number_format($data['ce']['delta'] ?? 0, 3) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} text-green-600">{{ number_format($data['ce']['gamma'] ?? 0, 4) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} text-orange-600">{{ number_format($data['ce']['theta'] ?? 0, 2) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} text-pink-600">{{ number_format($data['ce']['vega'] ?? 0, 2) }}</td>
                                <td class="p-1 text-center border-r {{ $ceItm }} {{ ($data['ce']['percentChange'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($data['ce']['percentChange'] ?? 0, 2) }}%
                                </td>
                                <td class="p-1 text-right border-r font-bold {{ $ceItm }} text-blue-700" data-price>
                                    {{ number_format($data['ce']['ltp'] ?? 0, 2) }}
                                </td>

                                <td class="p-1 text-center {{ $isAtm ? 'bg-indigo-600 text-white' : 'bg-gray-100' }} font-black text-sm border-x shadow-inner">
                                    {{ $strike }}
                                </td>

                                <td class="p-1 text-left border-l font-bold {{ $peItm }} text-blue-700" data-price>
                                    {{ number_format($data['pe']['ltp'] ?? 0, 2) }}
                                </td>
                                <td class="p-1 text-center border-l {{ $peItm }} {{ ($data['pe']['percentChange'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($data['pe']['percentChange'] ?? 0, 2) }}%
                                </td>
                                <td class="p-1 text-center border-l {{ $peItm }} text-pink-600">{{ number_format($data['pe']['vega'] ?? 0, 2) }}</td>
                                <td class="p-1 text-center border-l {{ $peItm }} text-orange-600">{{ number_format($data['pe']['theta'] ?? 0, 2) }}</td>
                                <td class="p-1 text-center border-l {{ $peItm }} text-green-600">{{ number_format($data['pe']['gamma'] ?? 0, 4) }}</td>
                                <td class="p-1 text-center border-l {{ $peItm }} text-purple-600 font-medium">{{ number_format($data['pe']['delta'] ?? 0, 3) }}</td>
                                <td class="p-1 text-center border-l {{ $peItm }} text-gray-500">{{ number_format($data['pe']['iv'] ?? 0, 2) }}</td>
                                <td class="p-1 text-center border-l {{ $peItm }} text-blue-500">{{ number_format($data['pe']['volume'] ?? 0, 0) }}</td>
                                <td class="p-1 text-center {{ $peItm }}">{{ number_format($data['pe']['oi'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="19" class="p-8 text-center text-gray-400 italic">
                                    No live data available. Please check your API connection or Market Hours.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let isMarketOpen = {{ isset($marketStatus['is_open']) ? json_encode($marketStatus['is_open']) : 'false' }};
        let refreshInterval;
        
        function refreshData() {
            const spinner = document.getElementById('spinner');
            const btn = document.getElementById('refreshBtn');
            const lastUpdate = document.getElementById('lastUpdate');
            
            spinner.classList.remove('hidden');
            btn.disabled = true;
            
            // Add price change animation
            const priceElements = document.querySelectorAll('[data-price]');
            priceElements.forEach(el => {
                el.style.transition = 'color 0.3s';
                el.style.color = '#f59e0b'; // Amber color for updating
            });
            
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update the table body
                    const newTableBody = doc.getElementById('optionsTableBody');
                    const currentTableBody = document.getElementById('optionsTableBody');
                    if (newTableBody && currentTableBody) {
                        currentTableBody.innerHTML = newTableBody.innerHTML;
                        
                        // Flash updated prices
                        setTimeout(() => {
                            const updatedPrices = currentTableBody.querySelectorAll('[data-price]');
                            updatedPrices.forEach(el => {
                                el.style.color = '#10b981'; // Green for updated
                                setTimeout(() => {
                                    el.style.color = '';
                                }, 1000);
                            });
                        }, 100);
                    }
                    
                    // Update last refresh time
                    lastUpdate.textContent = 'Refreshed: ' + new Date().toLocaleTimeString();
                })
                .catch(error => {
                    console.error('Refresh error:', error);
                })
                .finally(() => {
                    spinner.classList.add('hidden');
                    btn.disabled = false;
                });
        }
        
        // Auto-refresh logic
        function startAutoRefresh() {
            if (isMarketOpen) {
                // Refresh every 5 seconds during market hours
                refreshInterval = setInterval(() => {
                    refreshData();
                }, 5000);
            } else {
                // Refresh every 30 seconds when market is closed
                refreshInterval = setInterval(() => {
                    refreshData();
                }, 30000);
            }
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Stop auto-refresh when user leaves the page
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                }
            });
        });
    </script>
</x-app-layout>