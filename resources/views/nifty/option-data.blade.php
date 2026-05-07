 <x-app-layout>
@push('styles')
    @vite(['resources/css/option-chain-ui.css'])
@endpush

<div class="py-4">

    <div class="max-w-full mx-auto sm:px-4 lg:px-6">

 
 
        <!-- Header -->


        <div class="bg-white rounded-2xl shadow overflow-hidden border border-gray-200">

            <!-- YOUR OPTION CHAIN TABLE / CONTENT HERE -->

            {{-- 2. OPTION CHAIN TABLE --}}
            <div class="mt-3 overflow-x-auto shadow-2xl rounded-lg border border-gray-300 bg-white">
                <table class="w-full text-[12px] border-collapse uppercase tracking-tight" id="optionChainTable">
                    <thead>
                        <tr class="bg-gray-900 text-white text-center">
                            <th class="py-2.5 border-r border-gray-700 text-green-400 tracking-widest w-1/3">▲ CE LTP</th>
                            <th class="bg-indigo-900 text-white text-xs font-black w-1/3">STRIKE</th>
                            <th class="py-2.5 border-l border-gray-700 text-red-400 tracking-widest w-1/3">PE LTP ▼</th>
                        </tr>
                    </thead>
                    <tbody id="chainBody">
                        @forelse($optionsData as $strike => $data)
                            @php
                                $isAtm   = (isset($niftySpot) && abs($niftySpot - $strike) <= 25);
                                $ceItm   = (isset($niftySpot) && $strike < $niftySpot) ? 'bg-orange-50/70' : '';
                                $peItm   = (isset($niftySpot) && $strike > $niftySpot) ? 'bg-orange-50/70' : '';
                                $ceToken = $data['ce']['symbol_token'] ?? null;
                                $peToken = $data['pe']['symbol_token'] ?? null;
                                $ceChg   = $data['ce']['percentChange'] ?? 0;
                                $peChg   = $data['pe']['percentChange'] ?? 0;
                            @endphp

                            <tr class="group border-b hover:bg-indigo-50/60 transition-colors {{ $isAtm ? 'atm-row ring-2 ring-indigo-400 ring-inset' : '' }}"
                                data-strike="{{ $strike }}"
                                data-ce-oi="{{ $data['ce']['oi'] ?? 0 }}"
                                data-pe-oi="{{ $data['pe']['oi'] ?? 0 }}">

                                {{-- CE --}}
                                <td class="p-2.5 border-r {{ $ceItm }} bg-green-50/40">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($ceToken)
                                            <button
                                                onclick="openAngelChart('{{ $ceToken }}','NIFTY {{ $strike }} CE','NFO','{{ $peToken }}',{{ $strike }},'CE','{{ $selectedExpiry }}')"
                                                class="opacity-0 group-hover:opacity-100 p-0.5 rounded bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition-all flex-shrink-0"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 12l3-3 3 3 4-4M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z"/>
                                                </svg>
                                            </button>
                                        @endif
                                        <div class="text-right">
                                            <div data-ltp="ce" class="font-black text-green-700 text-[14px]">{{ number_format($data['ce']['ltp'] ?? 0, 2) }}</div>
                                            <div data-chg="ce" class="text-[9px] leading-tight {{ $ceChg >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $ceChg >= 0 ? '▲' : '▼' }}{{ abs(round($ceChg, 2)) }}%
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- STRIKE --}}
                                <td class="p-0 text-center {{ $isAtm ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800' }} border-x font-black" data-strike-td>
                                    <span class="flex items-center justify-center w-full py-3 text-[13px]">{{ number_format($strike) }}</span>
                                </td>

                                {{-- PE --}}
                                <td class="p-2.5 border-l {{ $peItm }} bg-red-50/40">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-left">
                                            <div data-ltp="pe" class="font-black text-red-700 text-[14px]">{{ number_format($data['pe']['ltp'] ?? 0, 2) }}</div>
                                            <div data-chg="pe" class="text-[9px] leading-tight {{ $peChg >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $peChg >= 0 ? '▲' : '▼' }}{{ abs(round($peChg, 2)) }}%
                                            </div>
                                        </div>
                                        <div class="flex gap-0.5 flex-shrink-0">
                                            @if($peToken)
                                                <button
                                                    onclick="openAngelChart('{{ $peToken }}','NIFTY {{ $strike }} PE','NFO','{{ $ceToken }}',{{ $strike }},'PE','{{ $selectedExpiry }}')"
                                                    class="opacity-0 group-hover:opacity-100 p-0.5 rounded bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition-all"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 12l3-3 3 3 4-4M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-12 text-center text-gray-400 uppercase tracking-widest">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-8 h-8 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
                                        Loading Market Data...
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
 
        </div>
    </div>
</div>

<!-- CHART MODAL -->
<div id="chartModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4"
     onclick="handleBackdropClick(event)">

    <div id="modalBox"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-[98vw] h-[95vh] overflow-hidden flex flex-col">

        <!-- HEADER -->
        <div class="flex items-center justify-between px-5 py-3 border-b bg-white">

            <div class="flex items-center gap-3">

                <h2 id="modalTitle"
                    class="text-lg font-bold text-slate-800">
                    Option Chart
                </h2>

                <div id="liveChip"
                     class="hidden px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">
                    LIVE
                </div>
            </div>

            <div class="flex items-center gap-2">

                <!-- INTERVAL -->
                <button class="iv-btn px-3 py-1 rounded bg-indigo-600 text-white text-xs"
                        data-iv="THREE_MINUTE"
                        onclick="changeInterval('THREE_MINUTE')">
                    3m
                </button>

                <button class="iv-btn px-3 py-1 rounded bg-gray-100 text-gray-500 text-xs"
                        data-iv="FIVE_MINUTE"
                        onclick="changeInterval('FIVE_MINUTE')">
                    5m
                </button>

                <button class="iv-btn px-3 py-1 rounded bg-gray-100 text-gray-500 text-xs"
                        data-iv="FIFTEEN_MINUTE"
                        onclick="changeInterval('FIFTEEN_MINUTE')">
                    15m
                </button>

                <!-- CHART TYPE -->
                <button id="btnCandle"
                        onclick="setChartType('candlestick')"
                        class="px-3 py-1 rounded bg-indigo-600 text-white text-xs">
                    Candle
                </button>

                <button id="btnLine"
                        onclick="setChartType('line')"
                        class="px-3 py-1 rounded bg-gray-100 text-gray-500 text-xs">
                    Line
                </button>

                <!-- CLOSE -->
                <button onclick="closeModal()"
                        class="text-2xl text-slate-400 hover:text-red-500 leading-none">
                    ×
                </button>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="flex flex-1 overflow-hidden relative">

            <!-- LEFT CHART -->
            <div class="flex-1 flex flex-col border-r bg-white relative">

                <!-- LOADER -->
                <div id="chartLoader"
                     class="hidden absolute inset-0 items-center justify-center bg-white/80 z-20">

                    <div class="text-center">
                        <div class="w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>

                        <p class="mt-3 text-sm text-gray-500">
                            Loading chart...
                        </p>
                    </div>
                </div>

                <!-- ERROR -->
                <div id="chartError"
                     class="hidden absolute inset-0 items-center justify-center bg-white z-30">

                    <div class="text-center">

                        <p id="errMsg"
                           class="text-red-500 text-sm mb-3">
                        </p>

                        <button onclick="retryLoad()"
                                class="px-4 py-2 rounded-lg bg-indigo-600 text-white">
                            Retry
                        </button>
                    </div>
                </div>

                <!-- OHLC -->
                <div id="ohlcBar"
                     class="hidden items-center gap-4 px-4 py-2 text-xs border-b bg-slate-50 text-slate-700">

                    <span id="ohlcLabel"></span>

                    <span>O: <b id="oVal"></b></span>
                    <span>H: <b id="hVal"></b></span>
                    <span>L: <b id="lVal"></b></span>
                    <span>C: <b id="cVal"></b></span>
                    <span>Vol: <b id="volVal"></b></span>

                    <span id="changeTag"
                          class="ml-auto font-semibold">
                    </span>
                </div>

                <!-- MAIN CHART -->
                <div id="mainChart"
                     class="flex-1 min-h-0">
                </div>

                <!-- FOOTER -->
                <div class="flex items-center justify-between px-4 py-2 border-t bg-gray-50">

                    <div id="candleCount"
                         class="text-xs text-gray-500">
                    </div>
                </div>
            </div>

            <!-- RIGHT AI -->
            <div class="w-[340px] bg-white flex flex-col">

                <!-- AI HEADER -->
                <div class="flex items-center justify-between px-4 py-3 border-b bg-slate-100">

                    <div class="text-xs font-bold tracking-wide text-slate-700">
                        LIVE AI ANALYSIS
                    </div>

                    <button onclick="generateChartAIAnalysis()"
                            class="px-3 py-1.5 rounded-full bg-indigo-600 text-white text-xs font-semibold">
                        ⚡ ANALYZE
                    </button>
                </div>

                <!-- AI CONTENT -->
                <div id="aiAnalysisContent"
                     class="flex-1 overflow-y-auto px-4 py-4 text-sm text-gray-700 whitespace-pre-wrap">
                </div>

                <!-- AI LOADER -->
                <div id="aiAnalysisLoader"
                     class="hidden px-4 py-3 text-xs text-gray-500 border-t">
                    AI analyzing...
                </div>

                <!-- CHAT INPUT -->
                <div class="border-t p-3 bg-white">

                    <div class="flex items-center gap-2">

                        <input type="text"
                               id="aiChatInput"
                               placeholder="Ask AI..."
                               class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">

                        <button onclick="sendAIChatMessage()"
                                class="w-12 h-12 rounded-lg bg-indigo-600 text-white text-lg">
                            ➤
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LIBRARIES -->
<script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>


<script>
    window.OPTION_CHAIN_ROUTE = "{{ route('angel.nifty.option-chain') }}";
</script>

<!-- VITE -->
@vite([
'resources/js/option-chain.js',
'resources/js/option-chain-chart.js',
'resources/js/nifty-option-data-ai-chat.js'
])


</x-app-layout>



