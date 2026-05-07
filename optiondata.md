<x-app-layout>
<div class="py-4">
    <div class="max-w-full mx-auto sm:px-4 lg:px-6">

        {{-- ═══ HEADER BAR ═══ --}}
        <div class="flex flex-wrap gap-3 border-t-2 border-indigo-500 border-b-2 border-indigo-500 py-2.5 justify-between items-center bg-white px-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="uppercase font-extrabold text-indigo-900 tracking-wider text-sm">📊 Nifty Option Chain</h2>
                <div class="flex items-center gap-1.5">
                    <label class="text-[10px] font-bold text-gray-500 uppercase">Expiry:</label>
                    <select onchange="changeExpiry(this.value)" class="text-xs font-bold border-gray-300 rounded py-1 px-2 bg-gray-50">
                        @foreach($allExpiries as $expiry)
                            <option value="{{ $expiry }}" {{ $selectedExpiry == $expiry ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($expiry)->format('d-M-Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <label class="text-[10px] font-bold text-gray-500 uppercase">Auto Refresh:</label>
                    <button id="autoRefreshBtn" onclick="toggleAutoRefresh()"
                        class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-200 text-gray-600 hover:bg-gray-300 transition-all">OFF</button>
                    <span id="refreshCountdown" class="text-[10px] text-indigo-500 font-mono hidden"></span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-[11px] font-black px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                    PCR: <span id="pcrValue">—</span>
                    <span id="pcrSignal" class="ml-1 text-[10px]"></span>
                </div>
                <span id="marketStatusBadge" class="text-[10px] font-bold px-2 py-1 rounded-full transition-all"></span>
                @if(isset($niftySpot))
                    <div class="text-sm font-black text-gray-800">
                        NIFTY: <span id="niftySpotValue" class="text-indigo-600 ml-1">{{ number_format($niftySpot, 2) }}</span>
                    </div>
                @endif
                <div class="text-[10px] text-gray-400 font-mono"><span id="lastUpdated"></span></div>
            </div>
        </div>

        {{-- ═══ OPTION CHAIN TABLE ═══ --}}
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
                            $isAtm   = (abs($niftySpot - $strike) <= 25);
                            $ceItm   = ($strike < $niftySpot) ? 'bg-orange-50/70' : '';
                            $peItm   = ($strike > $niftySpot) ? 'bg-orange-50/70' : '';
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
                                        {{-- ✅ expiry 7th argument add kiya --}}
                                        <button onclick="openAngelChart('{{ $ceToken }}','NIFTY {{ $strike }} CE','NFO','{{ $peToken }}',{{ $strike }},'CE','{{ $selectedExpiry }}')"
                                            class="opacity-0 group-hover:opacity-100 p-0.5 rounded bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition-all flex-shrink-0">
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
                                            {{-- ✅ expiry 7th argument add kiya --}}
                                            <button onclick="openAngelChart('{{ $peToken }}','NIFTY {{ $strike }} PE','NFO','{{ $ceToken }}',{{ $strike }},'PE','{{ $selectedExpiry }}')"
                                                class="opacity-0 group-hover:opacity-100 p-0.5 rounded bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 12l3-3 3 3 4-4M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z"/>
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

        <div class="mt-2 flex flex-wrap items-center gap-5 px-2 text-[10px] text-gray-500">
            <span>🟠 <span class="font-bold text-orange-500">Orange</span> = ITM</span>
            <span>🔵 <span class="font-bold text-indigo-600">Indigo ring</span> = ATM</span>
            <span>📊 CE OI: <span class="font-bold text-green-700" id="totalCeOI">—</span></span>
            <span>📊 PE OI: <span class="font-bold text-red-700" id="totalPeOI">—</span></span>
            <span>⚖️ PCR: <span class="font-bold text-indigo-700" id="footerPcr">—</span></span>
        </div>
    </div>
</div>

{{-- ════════════════════ CHART MODAL ════════════════════ --}}
<div id="chartModal"
     class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center backdrop-blur-sm"
     onclick="handleBackdropClick(event)">

    <div id="modalBox" class="rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200"
         style="width:97vw;max-width:1460px;height:91vh;background:#f8fafc;">

        {{-- ── HEADER ── --}}
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 flex-shrink-0 gap-3 flex-wrap"
             style="background:#ffffff;">
            <div class="flex items-center gap-3 flex-wrap">
                <h3 id="modalTitle" class="font-black uppercase tracking-widest text-sm text-gray-800"></h3>
                <span id="liveChip" class="hidden text-[9px] font-bold px-2 py-0.5 rounded-full bg-green-500 text-white animate-pulse">● LIVE</span>
                <div class="flex gap-1" id="intervalBtns">
                    @foreach(['THREE_MINUTE'=>'3m','FIVE_MINUTE'=>'5m','FIFTEEN_MINUTE'=>'15m'] as $val=>$lbl)
                        <button onclick="changeInterval('{{ $val }}')" data-iv="{{ $val }}"
                            class="iv-btn text-[10px] font-bold px-2.5 py-1 rounded transition-colors {{ $val==='FIVE_MINUTE'?'bg-indigo-600 text-white':'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
                <button id="compareBtn" onclick="toggleCompare()"
                    class="text-[10px] font-bold px-2.5 py-1 rounded bg-gray-100 text-gray-500 hover:bg-purple-100 hover:text-purple-700 transition-colors hidden">
                    ⇄ CE vs PE
                </button>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <div class="flex bg-gray-100 rounded overflow-hidden border border-gray-200">
                    <button onclick="setChartType('candlestick')" id="btnCandle"
                        class="text-[10px] px-2.5 py-1 font-bold text-white bg-indigo-600 transition-colors">▐ Candle</button>
                    <button onclick="setChartType('line')" id="btnLine"
                        class="text-[10px] px-2.5 py-1 font-bold text-gray-500 hover:text-gray-700 transition-colors border-l border-gray-200">⟋ Line</button>
                </div>
                <button onclick="takeScreenshot()" class="text-[10px] font-bold px-2.5 py-1 rounded bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors">📷</button>
                <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 font-black text-xl leading-none ml-1 transition-colors">✕</button>
            </div>
        </div>

        {{-- ── OHLC BAR ── --}}
        <div id="ohlcBar" class="hidden border-b border-gray-200 px-4 py-1.5 flex items-center gap-5 text-[10px] font-mono flex-shrink-0 flex-wrap"
             style="background:#f1f5f9;color:#475569;">
            <span class="text-indigo-600 font-bold" id="ohlcLabel"></span>
            <span>O <strong id="oVal" class="text-gray-800">—</strong></span>
            <span>H <strong id="hVal" class="text-green-700">—</strong></span>
            <span>L <strong id="lVal" class="text-red-600">—</strong></span>
            <span>C <strong id="cVal" class="text-gray-800">—</strong></span>
            <span>Vol <strong id="volVal" class="text-amber-700">—</strong></span>
            <strong id="changeTag"></strong>
        </div>

        {{-- ── MAIN BODY ── --}}
        <div class="flex flex-1 overflow-hidden min-h-0">

            {{-- ════ LEFT: CHART ════ --}}
            <div class="relative flex-1 overflow-hidden border-r border-gray-200 min-w-0" style="background:#ffffff;">
                <div id="mainChart" class="absolute inset-0"></div>

                <div id="chartLoader" class="absolute inset-0 flex flex-col items-center justify-center z-20" style="background:#f8fafc;">
                    <div class="w-10 h-10 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin mb-3"></div>
                    <p class="text-gray-500 text-[11px] uppercase tracking-widest">Fetching Data...</p>
                </div>

                <div id="chartError" class="hidden absolute inset-0 flex flex-col items-center justify-center z-20" style="background:#f8fafc;">
                    <div class="text-5xl mb-3">⚠️</div>
                    <p id="errMsg" class="text-red-600 text-sm text-center max-w-xs px-4"></p>
                    <button onclick="retryLoad()" class="mt-4 px-5 py-2 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 transition-colors">Retry</button>
                </div>
            </div>

            {{-- ════ RIGHT PANEL ════ --}}
            <div class="flex flex-col" style="width:340px;min-width:300px;max-width:340px;flex-shrink:0;background:#ffffff;">

                {{-- ══ AI ANALYSIS ══ --}}
                <div class="flex-shrink-0 border-b border-gray-200" style="max-height:55%;">
                    <div class="flex items-center gap-2 px-3 py-2.5 border-b border-gray-200" style="background:#f1f5f9;">
                        <span class="text-sm">🤖</span>
                        <span class="text-[10px] font-black uppercase tracking-widest flex-1" style="color:#1e293b;">Live AI Analysis</span>
                        <button id="aiAnalyzeBtn" onclick="runAIAnalysis()"
                            class="text-[9px] font-black px-3 py-1.5 rounded-full transition-all uppercase tracking-wide flex items-center gap-1 flex-shrink-0"
                            style="background:#4f46e5;color:#ffffff;cursor:pointer;">
                            <span id="aiAnalyzeBtnTxt">⚡ Analyze</span>
                        </button>
                    </div>

                    <div class="overflow-y-auto" style="max-height:calc(55vh - 50px);background:#ffffff;" id="aiResultArea">
                        <div id="aiWaiting" class="flex flex-col items-center justify-center gap-3 p-6 text-center" style="min-height:140px;">
                            <div style="font-size:28px;">📊</div>
                            <p class="text-[11px] leading-relaxed" style="color:#475569;">
                                Chart load hone ke baad<br>
                                <strong style="color:#4f46e5;">AI Analysis</strong> trigger hogi...
                            </p>
                        </div>

                        <div id="aiSkeleton" class="hidden p-3" style="background:#ffffff;">
                            <div class="rounded-lg mb-2" style="height:54px;background:linear-gradient(90deg,#f1f5f9,#e2e8f0,#f1f5f9);background-size:200%;animation:shimmer 1.4s infinite;"></div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                @for($i=0;$i<4;$i++)
                                <div class="rounded" style="height:42px;background:linear-gradient(90deg,#f1f5f9,#e2e8f0,#f1f5f9);background-size:200%;animation:shimmer 1.4s infinite;"></div>
                                @endfor
                            </div>
                        </div>

                        <div id="aiVerdictArea" class="hidden p-3" style="background:#ffffff;">
                            <div id="aiVerdictBox" class="flex items-center gap-2.5 p-2.5 rounded-lg mb-2 border">
                                <span id="aiIcon" class="text-2xl flex-shrink-0">📊</span>
                                <div class="flex-1 min-w-0">
                                    <div id="aiVerdictTitle" class="text-[12px] font-black uppercase leading-tight mb-0.5" style="color:#0f172a;"></div>
                                    <div id="aiConf" class="text-[10px] font-semibold" style="color:#334155;"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5 mb-2">
                                <div class="p-2 rounded border border-gray-200" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-wide mb-1" style="color:#334155;">Trend</div>
                                    <div id="aiTrendAlign" class="text-[12px] font-black" style="color:#0f172a;"></div>
                                </div>
                                <div class="p-2 rounded border border-gray-200" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-wide mb-1" style="color:#334155;">Momentum</div>
                                    <div id="aiMomentum" class="text-[12px] font-black" style="color:#0f172a;"></div>
                                </div>
                                <div class="p-2 rounded border border-gray-200" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-wide mb-1" style="color:#334155;">Volume</div>
                                    <div id="aiVolSig" class="text-[12px] font-black" style="color:#0f172a;"></div>
                                </div>
                                <div class="p-2 rounded border border-gray-200" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-wide mb-1" style="color:#334155;">Risk</div>
                                    <div id="aiRisk" class="text-[12px] font-black" style="color:#0f172a;"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5 mb-2" id="aiLevelsGrid" style="display:none;">
                                <div class="p-2 rounded border border-green-200" style="background:#f0fdf4;">
                                    <div class="text-[9px] font-black uppercase tracking-wide mb-1" style="color:#15803d;">Support</div>
                                    <div id="aiSupport" class="text-[12px] font-black" style="color:#15803d;">--</div>
                                </div>
                                <div class="p-2 rounded border border-red-200" style="background:#fef2f2;">
                                    <div class="text-[9px] font-black uppercase tracking-wide mb-1" style="color:#b91c1c;">Resistance</div>
                                    <div id="aiResist" class="text-[12px] font-black" style="color:#b91c1c;">--</div>
                                </div>
                            </div>
                            <p id="aiTimestamp" class="text-[9px] text-center pb-1" style="color:#94a3b8;"></p>
                        </div>
                    </div>
                </div>

                {{-- ══ CHAT ══ --}}
                <div class="flex flex-col flex-1 min-h-0">
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 flex-shrink-0" style="background:#f1f5f9;">
                        <span class="text-sm">💬</span>
                        <span class="text-[10px] font-black uppercase tracking-widest flex-1" style="color:#1e293b;">Ask AI</span>
                        <span id="chatStatusDot" class="text-[9px] font-bold" style="color:#16a34a;">● Ready</span>
                    </div>
                    <div id="chatMessages" class="flex-1 overflow-y-auto flex flex-col gap-2 p-3 min-h-0" style="background:#ffffff;"></div>
                    <div class="flex flex-wrap gap-1.5 px-3 pb-2 pt-2 flex-shrink-0 border-t border-gray-100" style="background:#f8fafc;">
                        <button onclick="quickAsk('Entry leni chahiye?')"  class="qchip">Entry?</button>
                        <button onclick="quickAsk('SL aur target kya?')"   class="qchip">SL & TP?</button>
                        <button onclick="quickAsk('Trend kya hai?')"       class="qchip">Trend?</button>
                        <button onclick="quickAsk('Volume kaisa hai?')"    class="qchip">Volume?</button>
                    </div>
                    <div class="flex gap-2 items-end px-3 pb-3 pt-2 flex-shrink-0 border-t border-gray-200" style="background:#f1f5f9;">
                        <textarea id="chatInput" rows="1" placeholder="Yahan poochein..."
                            onkeydown="handleChatKey(event)" oninput="chatAutoResize(this)"
                            class="flex-1 rounded-lg px-3 py-2 text-[11px] outline-none resize-none"
                            style="background:#ffffff;border:1px solid #cbd5e1;color:#0f172a;max-height:72px;min-height:32px;"></textarea>
                        <button id="chatSendBtn" onclick="sendChat()"
                            class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-white transition-all hover:scale-105"
                            style="background:#4f46e5;">➤</button>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── FOOTER ── --}}
        <div class="border-t border-gray-200 px-4 py-1.5 flex justify-between items-center flex-shrink-0" style="background:#f1f5f9;">
            <span id="candleCount" class="text-[10px] font-mono text-gray-500">—</span>
            <div class="flex items-center gap-3 text-[10px]">
                <span id="compareLabel" class="hidden text-purple-600 font-bold">⇄ CE vs PE Overlay Active</span>
                <span class="text-indigo-500 font-semibold">Angel One Historical API • NFO</span>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════ STYLES ════════════════════ --}}
<style>
@keyframes shimmer  { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
@keyframes fadeInUp { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:translateY(0)} }
@keyframes ltpFlash { 0%{background:rgba(99,102,241,.12)} 100%{background:transparent} }

#aiResultArea::-webkit-scrollbar,
#chatMessages::-webkit-scrollbar  { width:3px; }
#aiResultArea::-webkit-scrollbar-thumb,
#chatMessages::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }

.chat-msg { display:flex; flex-direction:column; gap:2px; animation:fadeInUp .2s ease; }
.chat-msg.user { align-items:flex-end; }
.chat-msg.bot  { align-items:flex-start; }
.chat-bubble   { max-width:88%; padding:7px 11px; border-radius:10px; font-size:11px; line-height:1.5; }
.chat-msg.user .chat-bubble { background:#3b82f6; color:#ffffff; border-radius:10px 10px 2px 10px; }
.chat-msg.bot  .chat-bubble { background:#f1f5f9; color:#1e293b; border:1px solid #e2e8f0; border-radius:10px 10px 10px 2px; }
.chat-time { font-size:8px; color:#94a3b8; padding:0 4px; }

.qchip {
    font-size:9px; padding:3px 10px; border-radius:99px;
    border:1px solid #e2e8f0; background:#ffffff; color:#475569;
    cursor:pointer; font-weight:700; transition:all .15s;
}
.qchip:hover { border-color:#6366f1; color:#4f46e5; background:#eef2ff; }

.av-bullish { background:#f0fdf4 !important; border-color:#4ade80 !important; }
.av-bearish { background:#fef2f2 !important; border-color:#f87171 !important; }
.av-neutral { background:#f8fafc !important; border-color:#cbd5e1 !important; }
.ltp-flash  { animation:ltpFlash .6s ease-out; }
</style>

{{-- ════════════════════ SCRIPTS ════════════════════ --}}
{{-- ✅ LightweightCharts CDN — standalone (no import needed) --}}
<script src="https://unpkg.com/lightweight-charts@4.1.0/dist/lightweight-charts.standalone.production.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

{{-- ✅ Global vars BEFORE Vite bundle loads --}}
<script>
    window.OPTION_CHAIN_ROUTE = "{{ route('angel.nifty.option-chain') }}";
    window._modalStrike = null;
    window._modalSide   = null;
    window._modalLabel  = '';
    window._modalExpiry = '';   // ✅ expiry global
    window._aiAnalyzing = false;
    window._chatTyping  = false;
    window._lastCandles = [];
</script>

{{-- ✅ Vite bundle (option-chain.js + option-chain-chart.js) --}}
@vite(['resources/css/option-chain.css','resources/js/option-chain.js'])

{{-- ✅ Inline script moved to dedicated file for cleanliness --}}
@vite(['resources/js/nifty-option-data-ai-chat.js'])



</x-app-layout>
