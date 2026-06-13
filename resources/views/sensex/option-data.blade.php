
@php
    use Carbon\Carbon;
@endphp

<x-app-layout>

<div class="py-4">

    <div class="max-w-full mx-auto sm:px-4 lg:px-6">

       
        <!-- Header -->
        <div class="flex flex-wrap gap-3 border-t-2 border-orange-500 border-b-2 border-orange-500 py-2.5 justify-between items-center bg-white px-4 shadow-sm">

            <!-- Left -->
            <div class="flex flex-wrap items-center gap-4">

                <!-- Title -->
                <h2 class="uppercase font-extrabold text-orange-900 tracking-wider text-sm">

                    📊 Sensex Option Chain

                </h2>

                <!-- Expiry -->
                <div class="flex items-center gap-1.5">

                    <label class="text-[10px] font-bold text-gray-500 uppercase">

                        Expiry:

                    </label>

                    <select
                        id="expirySelect"
                        onchange="changeSensexExpiry(this.value)"
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

                <flux:link href="{{ route('angel.option-data') }}">
                    📊 Open Nifty
                </flux:link>

            </div>

            <!-- Right -->
            <div class="flex items-center gap-3">

                <div class="text-sm font-black text-gray-800">

                    SENSEX:

                    <span
                        id="sensexSpotValue"
                        class="text-orange-600 ml-1"
                    >

                        {{ number_format($sensexSpot,2) }}

                    </span>

                </div>

            </div>

        </div>
        <!-- Header -->



        <!-- Main -->
        <div class="flex gap-3 mt-3" id="mainLayout">

            <div class="flex-1 min-w-0">

                @if(empty($allExpiries))

                    <div class="p-4 rounded-lg border border-orange-300 bg-orange-50 text-orange-800 text-sm font-mono mb-3">

                        ⚠️ <strong>Scrip cache not found.</strong>

                    </div>

                @endif

                <div class="overflow-x-auto shadow-2xl rounded-lg border border-gray-300 bg-white">

                    <table class="w-full text-[12px] border-collapse uppercase tracking-tight">

                        <thead>

                            <tr class="bg-gray-900 text-white text-center">

                                <th class="py-2.5 border-r border-gray-700 text-green-400 tracking-widest w-1/3">
                                    ▲ CE LTP
                                </th>

                                <th class="bg-orange-900 text-white text-xs font-black w-1/3">
                                    STRIKE
                                </th>

                                <th class="py-2.5 border-l border-gray-700 text-red-400 tracking-widest w-1/3">
                                    PE LTP ▼
                                </th>

                            </tr>

                        </thead>

                        <tbody id="chainBody">

                            @forelse($optionsData as $strike => $data)

                                @php

                                    /*
                                    |--------------------------------------------------------------------------
                                    | ATM FIX
                                    |--------------------------------------------------------------------------
                                    */

                                    $atmStrike =
                                        round(($sensexSpot ?? 0) / 100) * 100;

                                    $isAtm =
                                        ((int)$strike === (int)$atmStrike);

                                    /*
                                    |--------------------------------------------------------------------------
                                    | ITM
                                    |--------------------------------------------------------------------------
                                    */

                                    $ceItm =
                                        $strike < ($sensexSpot ?? 0)
                                        ? 'bg-orange-50/70'
                                        : '';

                                    $peItm =
                                        $strike > ($sensexSpot ?? 0)
                                        ? 'bg-orange-50/70'
                                        : '';

                                    /*
                                    |--------------------------------------------------------------------------
                                    | TOKENS
                                    |--------------------------------------------------------------------------
                                    */

                                    $ceToken =
                                        $data['ce']['symbol_token'] ?? '';

                                    $peToken =
                                        $data['pe']['symbol_token'] ?? '';

                                    $ceTokenJs =
                                        $ceToken
                                        ? "'" . addslashes($ceToken) . "'"
                                        : "''";

                                    $peTokenJs =
                                        $peToken
                                        ? "'" . addslashes($peToken) . "'"
                                        : "''";

                                    /*
                                    |--------------------------------------------------------------------------
                                    | DATA
                                    |--------------------------------------------------------------------------
                                    */

                                    $ceLtp =
                                        $data['ce']['ltp'] ?? 0;

                                    $peLtp =
                                        $data['pe']['ltp'] ?? 0;

                                    $ceChg =
                                        $data['ce']['percentChange'] ?? 0;

                                    $peChg =
                                        $data['pe']['percentChange'] ?? 0;

                                @endphp

                                <tr

                                    class="group border-b hover:bg-orange-50/60 transition-colors {{ $isAtm ? 'atm-row ring-2 ring-orange-400 ring-inset bg-orange-100/70' : '' }}"

                                    data-strike="{{ $strike }}"

                                    data-ce-token="{{ $ceToken }}"

                                    data-pe-token="{{ $peToken }}"

                                    data-ce-oi="{{ $data['ce']['oi'] ?? 0 }}"

                                    data-pe-oi="{{ $data['pe']['oi'] ?? 0 }}"
                                >

                                    {{-- CE --}}
                                    <td class="p-2.5 border-r {{ $ceItm }} bg-green-50/40">

                                        <div class="flex items-center justify-end gap-2">

                                            <div class="flex flex-col items-end">

                                                <div
                                                    data-ltp="ce"
                                                    class="font-black text-green-700"
                                                >

                                                    {{ $ceLtp > 0 ? number_format($ceLtp, 2) : '—' }}

                                                </div>

                                                <div class="text-[9px] {{ $ceChg >= 0 ? 'text-green-500' : 'text-red-500' }}">

                                                    {{ $ceLtp > 0 ? (($ceChg >= 0 ? '▲' : '▼') . abs(round($ceChg, 2)) . '%') : '' }}

                                                </div>

                                            </div>

                                        </div>

                                    </td>

                                    {{-- STRIKE --}}
                                    <td class="p-2 font-black text-gray-900 bg-gray-50">

                                        <div class="flex items-center justify-center gap-2">

                                            @if($isAtm)

                                                <span class="text-orange-600">
                                                    🔵
                                                </span>

                                            @endif

                                            {{ number_format($strike) }}

                                        </div>

                                    </td>

                                    {{-- PE --}}
                                    <td class="p-2.5 border-l {{ $peItm }} bg-red-50/40">

                                        <div class="flex items-center justify-between gap-2">

                                            <div class="flex flex-col items-start">

                                                <div
                                                    data-ltp="pe"
                                                    class="font-black text-red-700"
                                                >

                                                    {{ $peLtp > 0 ? number_format($peLtp, 2) : '—' }}

                                                </div>

                                                <div class="text-[9px] {{ $peChg >= 0 ? 'text-green-500' : 'text-red-500' }}">

                                                    {{ $peLtp > 0 ? (($peChg >= 0 ? '▲' : '▼') . abs(round($peChg, 2)) . '%') : '' }}

                                                </div>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td
                                        colspan="3"
                                        class="p-8 text-center text-gray-400 text-[11px] uppercase tracking-widest"
                                    >

                                        No option data

                                    </td>

                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

@vite(['resources/js/sensex-option-data.js'])
<!-- SENSEX CHART MODAL -->
    <div
        id="sensexChartModal"
        class="hidden fixed inset-0 z-50 bg-black/70"
    >

        <div
            class="bg-white w-[95%] h-[90vh] mx-auto mt-6 rounded-xl p-4"
        >

            <div class="flex justify-between mb-3">

                <h2
                    id="sensexChartTitle"
                    class="font-bold text-lg"
                >
                    SENSEX CHART
                </h2>

                <button
                    onclick="
                        document
                            .getElementById('sensexChartModal')
                            .classList.add('hidden')
                    "
                >
                    ✕
                </button>

            </div>

            <div
                id="sensexChartContainer"
                style="height:500px;"
            ></div>

        </div>
    </div>

</x-app-layout>

