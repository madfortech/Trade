 <x-app-layout>
@push('styles')
    @vite(['resources/css/option-chain-ui.css'])
@endpush

<div class="py-4">

    <div class="max-w-full mx-auto sm:px-4 lg:px-6">

 
        <!-- Header -->
           <div class="flex flex-wrap gap-3 border-t-2 border-orange-500 border-b-2 border-orange-500 py-2.5 justify-between items-center bg-white px-4 shadow-sm">

            <!-- Left -->
            <div class="flex flex-wrap items-center gap-4">

                <!-- Title -->
                <h2 class="uppercase font-extrabold text-orange-900 tracking-wider text-sm">

                    📊 Nifty Option Chain

                </h2>

                <!-- Expiry -->
                <div class="flex items-center gap-1.5">

                    <label class="text-[10px] font-bold text-gray-500 uppercase">

                        Expiry:

                    </label>

                    <select
                        id="expirySelect"
                        onchange="changeNiftyExpiry(this.value)"
                        class="text-xs font-bold border-gray-300 rounded py-1 px-2 bg-gray-50"
                    >

                        @forelse($allExpiries ?? [] as $expiry)

                            <option
                                value="{{ $expiry }}"
                                {{ ($selectedExpiry ?? '') == $expiry ? 'selected' : '' }}
                            >

                                @php

                                    try {

                                        echo \Carbon\Carbon::createFromFormat(
                                            'dMY',
                                            strtoupper($expiry)
                                        )->format('d-M-Y');

                                    } catch(\Exception $e) {

                                        echo $expiry;
                                    }

                                @endphp

                            </option>

                        @empty

                            <option value="">

                                No expiries

                            </option>

                        @endforelse

                    </select>

                </div>
                <!-- Expiry -->

                <!-- Auto Refresh -->
                <div class="flex items-center gap-1.5">

                    <label class="text-[10px] font-bold text-gray-500 uppercase">

                        Auto Refresh:

                    </label>

                    <button
                        id="autoRefreshBtn"
                        onclick="toggleAutoRefresh()"
                        class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-200 text-gray-600 hover:bg-gray-300 transition-all"
                    >

                        OFF

                    </button>

                    {{-- COUNTDOWN --}}
                    <span
                        id="refreshCountdown"
                        class="text-[10px] text-orange-500 font-mono hidden"
                    ></span>

                </div>

                <flux:link href="{{ route('sensex.option-chain') }}">
                    📊 Open Sensex
                </flux:link>

            </div>

            <!-- Right -->
            <div class="flex items-center gap-3">

                <div class="text-sm font-black text-gray-800">

                    NIFTY:

                    <span
                        id="niftySpotValue"
                        class="text-orange-600 ml-1"
                    >

                        {{ number_format($niftySpot,2) }}

                    </span>

                </div>

            </div>

        </div>
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
        		data-iv="FIVE_MINUTE"
        		onclick="changeInterval('FIVE_MINUTE')">
    			5m
		</button>

		<button class="iv-btn px-3 py-1 rounded bg-gray-100 text-gray-500 text-xs"
        		data-iv="ONE_HOUR"
        		onclick="changeInterval('ONE_HOUR')">
    			1H
		</button>

		<button class="iv-btn px-3 py-1 rounded bg-gray-100 text-gray-500 text-xs"
        		data-iv="ONE_DAY"
        		onclick="changeInterval('ONE_DAY')">
    			1D
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

                    <!-- CHART TIMEFRAMES -->
                    <div class="flex items-center gap-1 px-4 py-2 border-t bg-white text-xs">

                        <button
                            data-range="1D"
                            onclick="changeChartRange('1D')"
                            class="chart-range-btn
                                bg-indigo-600
                                text-white
                                px-2 py-1 rounded-md">
                            1D
                        </button>

                        <button
                            data-range="5D"
                            onclick="changeChartRange('5D')"
                            class="chart-range-btn
                                text-slate-500
                                hover:bg-slate-100
                                px-2 py-1 rounded-md">
                            5D
                        </button>

                        <button
                            data-range="1M"
                            onclick="changeChartRange('1M')"
                            class="chart-range-btn
                                text-slate-500
                                hover:bg-slate-100
                                px-2 py-1 rounded-md">
                            1M
                        </button>

                        <button
                            data-range="3M"
                            onclick="changeChartRange('3M')"
                            class="chart-range-btn
                                text-slate-500
                                hover:bg-slate-100
                                px-2 py-1 rounded-md">
                            3M
                        </button>

                        <button
                            data-range="6M"
                            onclick="changeChartRange('6M')"
                            class="chart-range-btn
                                text-slate-500
                                hover:bg-slate-100
                                px-2 py-1 rounded-md">
                            6M
                        </button>

                        <button
                            data-range="1Y"
                            onclick="changeChartRange('1Y')"
                            class="chart-range-btn
                                text-slate-500
                                hover:bg-slate-100
                                px-2 py-1 rounded-md">
                            1Y
                        </button>

                        <button
                            data-range="5Y"
                            onclick="changeChartRange('5Y')"
                            class="chart-range-btn
                                text-slate-500
                                hover:bg-slate-100
                                px-2 py-1 rounded-md">
                            5Y
                        </button>

                    </div>
                    <!-- END CHART TIMEFRAMES   -->
                </div>
            </div>

            <!-- RIGHT AI -->
            <div class="flex h-full min-h-0 w-[420px] min-w-[420px] max-w-[420px] flex-col overflow-hidden border-l bg-white">

                {{-- ══ AI ANALYSIS HEADER + RESULT (partial) ══ --}}
                <div class="px-4 py-3 border-b bg-slate-100 shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-bold tracking-wide text-slate-700">LIVE AI ANALYSIS</div>
                        <button
                            id="aiAnalyzeBtn"
                            type="button"
                            onclick="window.runAIAnalysis()"
                            class="px-3 py-1.5 rounded-full bg-indigo-600 text-white text-xs font-semibold"
                        >
                            ⚡ Analyze
                        </button>
                    </div>
                </div>
                                       
                <!-- START AI ANALYSIS RESULT -->
                <div class="flex-1 min-h-0 overflow-y-auto">
                    @include('nifty.partials.ai-analyis')
                </div>
                <!-- END AI ANALYSIS RESULT -->
                    
                <!-- API KEY -->
                                        
                <!--  END API KEY -->

                <!-- CHAT -->
                    @include('nifty.partials.ai-chat')
                <!-- END CHAT -->
            </div>
           
            <!-- End Right AI -->
        </div>
    </div>
</div>

<!-- LIBRARIES -->

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>


<script>
    window.OPTION_CHAIN_ROUTE = "{{ route('angel.chain.refresh') }}";
</script>

<!-- VITE -->
@vite([
    'resources/js/option-chain/index.js'
])
</x-app-layout>



