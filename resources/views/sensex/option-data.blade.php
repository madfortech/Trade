<x-app-layout>
<div class="py-4">
    <div class="max-w-full mx-auto sm:px-4 lg:px-6">

        {{-- HEADER --}}
        <div class="flex flex-wrap gap-3 border-t-2 border-orange-500 border-b-2 border-orange-500 py-2.5 justify-between items-center bg-white px-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="uppercase font-extrabold text-orange-900 tracking-wider text-sm">📊 Sensex Option Chain</h2>
                <div class="flex items-center gap-1.5">
                    <label class="text-[10px] font-bold text-gray-500 uppercase">Expiry:</label>
                    <select onchange="changeSensexExpiry(this.value)" class="text-xs font-bold border-gray-300 rounded py-1 px-2 bg-gray-50">
                        @forelse($allExpiries as $expiry)
                            <option value="{{ $expiry }}" {{ $selectedExpiry == $expiry ? 'selected' : '' }}>
                                @php try { echo \Carbon\Carbon::createFromFormat('dMY',$expiry)->format('d-M-Y'); } catch(\Exception $e){ echo $expiry; } @endphp
                            </option>
                        @empty
                            <option value="">No expiries — run: php artisan scrip:cache</option>
                        @endforelse
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <label class="text-[10px] font-bold text-gray-500 uppercase">Auto Refresh:</label>
                    <button id="autoRefreshBtn" onclick="toggleAutoRefresh()" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-200 text-gray-600 hover:bg-gray-300 transition-all">OFF</button>
                    <span id="refreshCountdown" class="text-[10px] text-orange-500 font-mono hidden"></span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-[11px] font-black px-3 py-1 rounded-full bg-orange-50 text-orange-700 border border-orange-200">PCR: <span id="pcrValue">—</span> <span id="pcrSignal" class="ml-1 text-[10px]"></span></div>
                <span id="mktStatusBadge" class="text-[10px] font-bold px-2 py-1 rounded-full {{ ($marketStatus['is_open']??false) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ ($marketStatus['is_open']??false) ? '● LIVE' : '○ CLOSED' }}</span>
                @if(!empty($sensexSpot) && $sensexSpot > 0)
                    <div class="text-sm font-black text-gray-800">SENSEX: <span id="sensexSpotValue" class="text-orange-600 ml-1">{{ number_format($sensexSpot,2) }}</span></div>
                @endif
                <button onclick="toggleChat()" class="text-[10px] font-bold px-3 py-1.5 rounded-full bg-orange-600 text-white hover:bg-orange-700 transition-all">🤖 AI Chat</button>
                <div class="text-[10px] text-gray-400 font-mono"><span id="lastUpdated"></span></div>
            </div>
        </div>

        {{-- MAIN --}}
        <div class="flex gap-3 mt-3" id="mainLayout">
            <div class="flex-1 min-w-0">
                @if(empty($allExpiries))
                    <div class="p-4 rounded-lg border border-orange-300 bg-orange-50 text-orange-800 text-sm font-mono mb-3">⚠️ <strong>Scrip cache not found.</strong><br><code class="mt-2 block bg-orange-100 px-3 py-2 rounded text-xs font-bold">php artisan scrip:cache</code></div>
                @endif
                <div class="overflow-x-auto shadow-2xl rounded-lg border border-gray-300 bg-white">
                    <table class="w-full text-[12px] border-collapse uppercase tracking-tight">
                        <thead>
                            <tr class="bg-gray-900 text-white text-center">
                                <th class="py-2.5 border-r border-gray-700 text-green-400 tracking-widest w-1/3">▲ CE LTP</th>
                                <th class="bg-orange-900 text-white text-xs font-black w-1/3">STRIKE</th>
                                <th class="py-2.5 border-l border-gray-700 text-red-400 tracking-widest w-1/3">PE LTP ▼</th>
                            </tr>
                        </thead>
                        <tbody id="chainBody">
                            @forelse($optionsData as $strike => $data)
                                @php
                                    $isAtm=$ceItm=$peItm='';
                                    if(!empty($sensexSpot)){$isAtm=abs($sensexSpot-$strike)<=50;$ceItm=$strike<$sensexSpot?'bg-orange-50/70':'';$peItm=$strike>$sensexSpot?'bg-orange-50/70':'';}
                                    $ceToken=$data['ce']['symbol_token']??null; $peToken=$data['pe']['symbol_token']??null;
                                    $ceChg=$data['ce']['percentChange']??0; $peChg=$data['pe']['percentChange']??0;
                                    $ceLtp=$data['ce']['ltp']??0; $peLtp=$data['pe']['ltp']??0;
                                @endphp
                                <tr class="group border-b hover:bg-orange-50/60 transition-colors {{ $isAtm ? 'atm-row ring-2 ring-orange-400 ring-inset' : '' }}" data-strike="{{ $strike }}" data-ce-token="{{ $ceToken }}" data-pe-token="{{ $peToken }}" data-ce-oi="{{ $data['ce']['oi']??0 }}" data-pe-oi="{{ $data['pe']['oi']??0 }}">
                                    <td class="p-2.5 border-r {{ $ceItm }} bg-green-50/40">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($ceToken)<button onclick="openSensexChart('{{ $ceToken }}','SENSEX {{ number_format($strike) }} CE','BFO','{{ $peToken }}',{{ $strike }},'CE')" class="opacity-0 group-hover:opacity-100 p-0.5 rounded bg-orange-100 text-orange-600 hover:bg-orange-200 transition-all flex-shrink-0"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 12l3-3 3 3 4-4M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z"/></svg></button>@endif
                                            <div class="text-right">
                                                <div data-ltp="ce" class="font-black text-green-700 text-[14px]">{{ $ceLtp>0?number_format($ceLtp,2):'—' }}</div>
                                                <div data-chg="ce" class="text-[9px] {{ $ceChg>=0?'text-green-500':'text-red-500' }}">{{ $ceLtp>0?(($ceChg>=0?'▲':'▼').abs(round($ceChg,2)).'%'):'' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-0 text-center {{ $isAtm?'bg-orange-600 text-white':'bg-gray-100 text-gray-800' }} border-x font-black"><span class="flex items-center justify-center w-full py-3 text-[13px]">{{ number_format($strike) }}</span></td>
                                    <td class="p-2.5 border-l {{ $peItm }} bg-red-50/40">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-left">
                                                <div data-ltp="pe" class="font-black text-red-700 text-[14px]">{{ $peLtp>0?number_format($peLtp,2):'—' }}</div>
                                                <div data-chg="pe" class="text-[9px] {{ $peChg>=0?'text-green-500':'text-red-500' }}">{{ $peLtp>0?(($peChg>=0?'▲':'▼').abs(round($peChg,2)).'%'):'' }}</div>
                                            </div>
                                            @if($peToken)<button onclick="openSensexChart('{{ $peToken }}','SENSEX {{ number_format($strike) }} PE','BFO','{{ $ceToken }}',{{ $strike }},'PE')" class="opacity-0 group-hover:opacity-100 p-0.5 rounded bg-orange-100 text-orange-600 hover:bg-orange-200 transition-all flex-shrink-0"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 12l3-3 3 3 4-4M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 01-2 2z"/></svg></button>@endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-8 text-center text-gray-400 text-[11px] uppercase tracking-widest">@if(empty($allExpiries)) Run <code class="bg-gray-100 px-2 py-0.5 rounded text-orange-600">php artisan scrip:cache</code> @else No option data @endif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-5 px-2 text-[10px] text-gray-500">
                    <span>🟠 <span class="font-bold text-orange-500">Orange</span> = ITM</span>
                    <span>🔵 <span class="font-bold text-orange-600">Ring</span> = ATM</span>
                    <span>📊 CE OI: <span class="font-bold text-green-700" id="totalCeOI">—</span></span>
                    <span>📊 PE OI: <span class="font-bold text-red-700" id="totalPeOI">—</span></span>
                    <span>⚖️ PCR: <span class="font-bold text-orange-700" id="footerPcr">—</span></span>
                </div>
            </div>

            {{-- SIDE AI CHAT PANEL --}}
            <div id="chatPanel" class="hidden flex-col bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden" style="width:340px;min-width:300px;max-width:360px;height:calc(100vh - 120px);position:sticky;top:16px;">
                <div class="bg-orange-700 px-4 py-2.5 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2"><span class="text-lg">🤖</span><div><div class="text-white font-black text-[12px] uppercase tracking-wider">SENSEX AI Assistant</div><div class="text-orange-200 text-[10px]">Groq • llama-3.3-70b</div></div></div>
                    <button onclick="toggleChat()" class="text-orange-200 hover:text-white text-lg font-black">✕</button>
                </div>
                <div id="chatMessages" class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50" style="min-height:0;">
                    <div class="text-center text-[10px] text-gray-400 py-2">Sensex option chain ke baare mein poochho</div>
                    <div class="flex flex-wrap gap-1.5 justify-center pb-1">
                        @foreach(['Kya abhi buy karna chahiye?','PCR ka matlab?','ATM strike konsa?','Market trend?','Best strike for CE?'] as $chip)
                            <button onclick="sendChip('{{ $chip }}')" class="text-[10px] px-2.5 py-1 rounded-full border border-orange-300 text-orange-700 bg-white hover:bg-orange-50 transition-colors">{{ $chip }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="border-t border-gray-200 p-2 flex gap-2 flex-shrink-0 bg-white">
                    <input id="chatInput" type="text" placeholder="Poochho kuch bhi..." onkeydown="if(event.key==='Enter')sendChat()" class="flex-1 text-[12px] border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-orange-400 bg-gray-50">
                    <button onclick="sendChat()" class="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-[12px] font-bold flex-shrink-0">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ CHART MODAL ══════════ --}}
<div id="sensexChartModal" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center backdrop-blur-sm" onclick="handleSensexBackdropClick(event)">
    {{-- ✅ width:1560px, right panel 400px --}}
    <div class="rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200" style="width:97vw;max-width:1560px;height:92vh;background:#f8fafc;">

        {{-- HEADER --}}
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 flex-shrink-0 gap-3" style="background:#fff;min-height:48px;">
            <div class="flex items-center gap-3 flex-wrap flex-1 min-w-0">
                <h3 id="sensexModalTitle" class="font-black uppercase tracking-widest text-sm text-gray-800 truncate max-w-xs"></h3>
                <span id="sensexLiveChip" class="hidden text-[9px] font-bold px-2 py-0.5 rounded-full bg-green-500 text-white animate-pulse flex-shrink-0">● LIVE</span>
                <div id="tickSpeedWrap" class="hidden items-center gap-1 flex-shrink-0">
                    <span class="text-[9px] text-gray-400 font-bold">TICK:</span>
                    <button onclick="setTickSpeed(1000)" data-ts="1000" class="sx-tick-btn text-[9px] font-bold px-2 py-0.5 rounded">1s</button>
                    <button onclick="setTickSpeed(2000)" data-ts="2000" class="sx-tick-btn text-[9px] font-bold px-2 py-0.5 rounded">2s</button>
                    <button onclick="setTickSpeed(5000)" data-ts="5000" class="sx-tick-btn text-[9px] font-bold px-2 py-0.5 rounded">5s</button>
                </div>
                <div class="flex gap-1 flex-shrink-0">
                    <button onclick="changeSensexInterval('THREE_MINUTE')"   data-iv="THREE_MINUTE"   class="sx-iv-btn text-[10px] font-bold px-2.5 py-1 rounded">3m</button>
                    <button onclick="changeSensexInterval('FIVE_MINUTE')"    data-iv="FIVE_MINUTE"    class="sx-iv-btn text-[10px] font-bold px-2.5 py-1 rounded">5m</button>
                    <button onclick="changeSensexInterval('FIFTEEN_MINUTE')" data-iv="FIFTEEN_MINUTE" class="sx-iv-btn text-[10px] font-bold px-2.5 py-1 rounded">15m</button>
                    <button onclick="changeSensexInterval('THIRTY_MINUTE')"  data-iv="THIRTY_MINUTE"  class="sx-iv-btn text-[10px] font-bold px-2.5 py-1 rounded">30m</button>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <div class="flex bg-gray-100 rounded overflow-hidden border border-gray-200">
                    <button onclick="setSensexChartType('candlestick')" id="sxBtnCandle" class="text-[10px] px-2.5 py-1.5 font-bold text-white" style="background:#ea580c;">▐ Candle</button>
                    <button onclick="setSensexChartType('line')" id="sxBtnLine" class="text-[10px] px-2.5 py-1.5 font-bold text-gray-500 border-l border-gray-200">⟋ Line</button>
                </div>
                <button onclick="closeSensexModal()" class="text-gray-400 hover:text-red-500 font-black text-xl leading-none ml-1">✕</button>
            </div>
        </div>

        {{-- OHLC BAR --}}
        <div id="sxOhlcBar" class="hidden border-b border-gray-200 px-4 py-1.5 flex items-center gap-4 text-[10px] font-mono flex-shrink-0 flex-wrap" style="background:#f1f5f9;color:#475569;min-height:32px;">
            <span class="font-bold flex-shrink-0" id="sxOhlcLabel" style="color:#ea580c;min-width:140px;"></span>
            <span class="flex-shrink-0">O <strong id="sxOVal" class="text-gray-800">—</strong></span>
            <span class="flex-shrink-0">H <strong id="sxHVal" class="text-green-700">—</strong></span>
            <span class="flex-shrink-0">L <strong id="sxLVal" class="text-red-600">—</strong></span>
            <span class="flex-shrink-0">C <strong id="sxCVal" class="text-gray-800">—</strong></span>
            <strong id="sxChangeTag" class="text-[10px] flex-shrink-0"></strong>
            <span id="sxLiveLtpBadge" class="hidden ml-auto flex items-center gap-1.5 px-2 py-0.5 rounded-full border border-green-300 bg-green-50 flex-shrink-0">
                <span class="w-1.5 h-1.5 rounded-full animate-pulse inline-block bg-green-500"></span>
                <span class="font-black text-[11px] text-green-700" id="sxLiveLtpVal">—</span>
            </span>
        </div>

        {{-- BODY --}}
        <div class="flex flex-1 overflow-hidden min-h-0">

            {{-- CHART --}}
            <div class="relative flex-1 overflow-hidden border-r border-gray-200 min-w-0" style="background:#fff;">
                <div id="sensexChart" class="absolute inset-0"></div>
                <div id="sxChartLoader" class="absolute inset-0 flex flex-col items-center justify-center z-20" style="background:#f8fafc;">
                    <div class="w-10 h-10 border-4 border-orange-400 border-t-transparent rounded-full animate-spin mb-3"></div>
                    <p class="text-gray-500 text-[11px] uppercase tracking-widest">Fetching Data...</p>
                </div>
                <div id="sxChartError" class="hidden absolute inset-0 flex flex-col items-center justify-center z-20" style="background:#f8fafc;">
                    <div class="text-5xl mb-3">⚠️</div>
                    <p id="sxErrMsg" class="text-red-600 text-sm text-center max-w-xs px-4"></p>
                    <button onclick="retrySensexLoad()" class="mt-4 px-5 py-2 bg-orange-600 text-white text-xs rounded-lg hover:bg-orange-700">Retry</button>
                </div>
            </div>

            {{-- ✅ RIGHT PANEL: width:400px, proper flex layout --}}
            <div class="flex flex-col flex-shrink-0" style="width:400px;background:#fff;border-left:1px solid #e2e8f0;">

                {{-- AI ANALYSIS --}}
                <div class="flex flex-col border-b border-gray-200" style="flex:0 0 auto;max-height:56%;">

                    {{-- AI Header --}}
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 flex-shrink-0" style="background:#f1f5f9;">
                        <span class="text-sm flex-shrink-0">🤖</span>
                        <span class="text-[10px] font-black uppercase tracking-widest flex-1" style="color:#1e293b;">Live AI Analysis</span>
                        <button id="sxAnalyzeBtn" onclick="runSensexAiAnalyze()" class="text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-wide flex items-center gap-1 flex-shrink-0" style="background:#ea580c;color:#fff;cursor:pointer;">
                            <span id="sxAnalyzeBtnTxt">⚡ RE-ANALYZE</span>
                        </button>
                    </div>

                    {{-- AI Scroll --}}
                    <div id="sxAiResultArea" class="overflow-y-auto" style="background:#fff;flex:1;">

                        {{-- Waiting --}}
                        <div id="sxAiWaiting" class="flex flex-col items-center justify-center gap-2 p-5 text-center" style="min-height:120px;">
                            <div style="font-size:24px;">📊</div>
                            <p class="text-[11px] leading-relaxed" style="color:#475569;">Chart load hone ke baad<br><strong style="color:#ea580c;">AI Analysis</strong> auto trigger hogi</p>
                        </div>

                        {{-- Skeleton --}}
                        <div id="sxAiSkeleton" class="hidden p-3 space-y-2">
                            <div class="rounded-lg" style="height:52px;background:linear-gradient(90deg,#f1f5f9,#e2e8f0,#f1f5f9);background-size:200%;animation:shimmer 1.4s infinite;"></div>
                            <div class="grid grid-cols-2 gap-2">
                                @for($i=0;$i<4;$i++)<div class="rounded" style="height:52px;background:linear-gradient(90deg,#f1f5f9,#e2e8f0,#f1f5f9);background-size:200%;animation:shimmer 1.4s infinite;"></div>@endfor
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                @for($i=0;$i<2;$i++)<div class="rounded" style="height:44px;background:linear-gradient(90deg,#f1f5f9,#e2e8f0,#f1f5f9);background-size:200%;animation:shimmer 1.4s infinite;"></div>@endfor
                            </div>
                        </div>

                        {{-- ✅ VERDICT AREA: proper 2x2 grid, no overflow --}}
                        <div id="sxAiVerdictArea" class="hidden p-3 space-y-2">

                            {{-- Verdict banner --}}
                            <div id="sxAiVerdictBox" class="flex items-center gap-3 p-3 rounded-lg border">
                                <span id="sxAiIcon" class="text-2xl flex-shrink-0">📊</span>
                                <div class="flex-1 min-w-0">
                                    <div id="sxAiTitle" class="text-[13px] font-black uppercase leading-tight" style="color:#0f172a;"></div>
                                    <div id="sxAiConfidence" class="text-[10px] font-semibold mt-0.5" style="color:#334155;"></div>
                                </div>
                            </div>

                            {{-- ✅ 2x2 grid: Trend | Momentum / Volume | Risk --}}
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-lg border border-gray-200 p-2.5" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-widest mb-1" style="color:#64748b;">📈 Trend</div>
                                    <div id="sxAiTrend" class="text-[12px] font-black leading-snug" style="color:#0f172a;word-break:break-word;"></div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-2.5" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-widest mb-1" style="color:#64748b;">⚡ Momentum</div>
                                    <div id="sxAiMomentum" class="text-[12px] font-black leading-snug" style="color:#0f172a;word-break:break-word;"></div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-2.5" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-widest mb-1" style="color:#64748b;">📊 Volume</div>
                                    <div id="sxAiVol" class="text-[12px] font-black leading-snug" style="color:#0f172a;word-break:break-word;"></div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-2.5" style="background:#f8fafc;">
                                    <div class="text-[9px] font-black uppercase tracking-widest mb-1" style="color:#64748b;">⚠️ Risk</div>
                                    <div id="sxAiRisk" class="text-[12px] font-black leading-snug" style="color:#0f172a;word-break:break-word;"></div>
                                </div>
                            </div>

                            {{-- ✅ Support / Resistance: separate row, clearly labeled, big values --}}
                            <div class="grid grid-cols-2 gap-2" id="sxAiLevelsGrid" style="display:none;">
                                <div class="rounded-lg border border-green-200 p-2.5" style="background:#f0fdf4;">
                                    <div class="text-[9px] font-black uppercase tracking-widest mb-1" style="color:#15803d;">🟢 Support</div>
                                    <div id="sxAiSupport" class="text-[14px] font-black" style="color:#15803d;">--</div>
                                </div>
                                <div class="rounded-lg border border-red-200 p-2.5" style="background:#fef2f2;">
                                    <div class="text-[9px] font-black uppercase tracking-widest mb-1" style="color:#b91c1c;">🔴 Resistance</div>
                                    <div id="sxAiResist" class="text-[14px] font-black" style="color:#b91c1c;">--</div>
                                </div>
                            </div>

                            <p id="sxAiTimestamp" class="text-[9px] text-center" style="color:#94a3b8;"></p>
                        </div>
                    </div>
                </div>

                {{-- ASK AI CHAT --}}
                <div class="flex flex-col" style="flex:1;min-height:0;">
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 flex-shrink-0" style="background:#f1f5f9;">
                        <span class="text-sm flex-shrink-0">💬</span>
                        <span class="text-[10px] font-black uppercase tracking-widest flex-1" style="color:#1e293b;">Ask AI</span>
                        <span id="sxChatStatusDot" class="text-[9px] font-bold flex-shrink-0" style="color:#16a34a;">● Ready</span>
                    </div>
                    <div id="sxModalChatMsgs" class="overflow-y-auto flex flex-col gap-2 p-3" style="flex:1;min-height:0;background:#fff;"></div>
                    <div class="flex flex-wrap gap-1.5 px-3 py-2 flex-shrink-0 border-t border-gray-100" style="background:#f8fafc;">
                        <button onclick="sendModalChip('SL kahan rakhu?')"  class="sx-qchip">SL?</button>
                        <button onclick="sendModalChip('Entry point?')"     class="sx-qchip">Entry?</button>
                        <button onclick="sendModalChip('Target kya hai?')"  class="sx-qchip">Target?</button>
                        <button onclick="sendModalChip('Trend kaisa hai?')" class="sx-qchip">Trend?</button>
                    </div>
                    <div class="flex gap-2 items-center px-3 pb-3 pt-2 flex-shrink-0 border-t border-gray-200" style="background:#f1f5f9;">
                        <input id="sxModalChatInput" type="text" placeholder="Yahan poochhen..." onkeydown="if(event.key==='Enter')sendModalChat()" class="flex-1 rounded-lg px-3 py-2 text-[11px] outline-none" style="background:#fff;border:1px solid #cbd5e1;color:#0f172a;">
                        <button onclick="sendModalChat()" class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-white" style="background:#ea580c;">➤</button>
                    </div>
                </div>

            </div>{{-- /right panel --}}
        </div>{{-- /body --}}

        {{-- FOOTER --}}
        <div class="border-t border-gray-200 px-4 py-1.5 flex justify-between items-center flex-shrink-0" style="background:#f1f5f9;">
            <span id="sxCandleCount" class="text-[10px] font-mono text-gray-500">—</span>
            <div class="flex items-center gap-3">
                <span id="sxTickStatus" class="text-[10px] font-mono text-gray-500">—</span>
                <span class="text-[10px] text-orange-500 font-semibold">Angel One Historical API • BFO</span>
            </div>
        </div>

    </div>
</div>

{{-- ══════════ STYLES ══════════ --}}
<style>
@keyframes shimmer  { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
@keyframes ltpFlash { 0%{background:rgba(234,88,12,.12)} 100%{background:transparent} }
@keyframes ltpUp    { 0%{background:rgba(34,197,94,.18)} 100%{background:transparent} }
@keyframes ltpDown  { 0%{background:rgba(239,68,68,.18)} 100%{background:transparent} }
.ltp-flash    { animation:ltpFlash .6s ease-out; }
.ltp-flash-up { animation:ltpUp   .5s ease-out; }
.ltp-flash-dn { animation:ltpDown .5s ease-out; }
#sxAiResultArea::-webkit-scrollbar,#sxModalChatMsgs::-webkit-scrollbar,#chatMessages::-webkit-scrollbar{width:3px;}
#sxAiResultArea::-webkit-scrollbar-thumb,#sxModalChatMsgs::-webkit-scrollbar-thumb,#chatMessages::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px;}
.sx-av-bullish{background:#f0fdf4!important;border-color:#4ade80!important;}
.sx-av-bearish{background:#fef2f2!important;border-color:#f87171!important;}
.sx-av-neutral{background:#f8fafc!important;border-color:#cbd5e1!important;}
.modal-bubble-user{background:#ea580c;color:#fff;border-radius:8px 8px 2px 8px;font-size:11px;padding:6px 10px;line-height:1.5;}
.modal-bubble-ai{background:#f1f5f9;border:1px solid #e2e8f0;color:#1e293b;border-radius:8px 8px 8px 2px;font-size:11px;padding:6px 10px;line-height:1.5;}
.modal-bubble-err{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;border-radius:8px;font-size:11px;padding:6px 10px;}
.chat-bubble-user{background:#ea580c;color:#fff;border-radius:12px 12px 2px 12px;}
.chat-bubble-ai{background:#f1f5f9;border:1px solid #e2e8f0;color:#1e293b;border-radius:12px 12px 12px 2px;}
.chat-bubble-err{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;border-radius:12px;}
.sx-qchip{font-size:9px;padding:3px 10px;border-radius:99px;border:1px solid #e2e8f0;background:#fff;color:#475569;cursor:pointer;font-weight:700;transition:all .15s;white-space:nowrap;}
.sx-qchip:hover{border-color:#ea580c;color:#c2410c;background:#fff7ed;}
.sx-iv-btn,.sx-tick-btn{background:#1f2937;color:#9ca3af;transition:all .15s;}
</style>

{{-- ══════════ SCRIPTS ══════════ --}}
<script src="https://unpkg.com/lightweight-charts@4.1.0/dist/lightweight-charts.standalone.production.js"></script>
<script>
const SENSEX_BASE='/angel/sensex-option-chain',CANDLE_URL='/angel/sensex-candle-data',REFRESH_URL='/angel/sensex-chain-refresh',LIVE_TICK_URL='/angel/sensex-live-tick',SENSEX_AI_ANALYZE='/angel/sensex-ai-analyze',SENSEX_CHAT_URL='/angel/sensex-chat',SENSEX_CHART_CHAT='/angel/sensex-chart-chat';
const CSRF=document.querySelector('meta[name="csrf-token"]')?.content||'';

let G={chart:null,series:null,rawCandles:[],interval:'FIVE_MINUTE',token:null,exchange:'BFO',chartType:'candlestick',currentCandle:null,lastLtp:null,tickTimer:null,tickMs:2000,autoTimer:null,autoOn:false,expiry:'{{ $selectedExpiry }}',spot:{{ $sensexSpot??0 }},label:'',strike:0,side:'',chatHistory:[],chatOpen:false,modalChatHistory:[],modalChatTyping:false,sideChatTyping:false,aiAnalyzing:false};

document.addEventListener('DOMContentLoaded',()=>{_calcPCR();_styleIv('FIVE_MINUTE');_styleTick(2000);_styleChartBtn('candlestick');});

function _styleIv(a){document.querySelectorAll('.sx-iv-btn').forEach(b=>{const on=b.dataset.iv===a;b.style.background=on?'#ea580c':'#1f2937';b.style.color=on?'#fff':'#9ca3af';});}
function _styleTick(a){document.querySelectorAll('.sx-tick-btn').forEach(b=>{const on=parseInt(b.dataset.ts)===a;b.style.background=on?'#ea580c':'#1f2937';b.style.color=on?'#fff':'#9ca3af';});}
function _styleChartBtn(t){const c=t==='candlestick';document.getElementById('sxBtnCandle').style.cssText=c?'background:#ea580c;color:#fff;':'background:transparent;color:#9ca3af;';document.getElementById('sxBtnLine').style.cssText=c?'background:transparent;color:#9ca3af;':'background:#ea580c;color:#fff;';}

function _calcPCR(){
    let ce=0,pe=0;
    document.querySelectorAll('#chainBody tr[data-strike]').forEach(r=>{ce+=parseFloat(r.dataset.ceOi||0);pe+=parseFloat(r.dataset.peOi||0);});
    const pEl=document.getElementById('pcrValue'),sEl=document.getElementById('pcrSignal'),fEl=document.getElementById('footerPcr');
    if(ce===0&&pe===0){[pEl,fEl].forEach(e=>e&&(e.textContent='—'));if(sEl)sEl.textContent='⚪ No Data';document.getElementById('totalCeOI').textContent='—';document.getElementById('totalPeOI').textContent='—';return;}
    const pcr=ce>0?(pe/ce).toFixed(2):'—';[pEl,fEl].forEach(e=>e&&(e.textContent=pcr));
    document.getElementById('totalCeOI').textContent=_fmt(ce);document.getElementById('totalPeOI').textContent=_fmt(pe);
    const n=parseFloat(pcr);if(sEl)sEl.textContent=n>1.2?'🟢 Bullish':n<0.8?'🔴 Bearish':'⚪ Neutral';
}
function _fmt(n){if(n>=1e7)return(n/1e7).toFixed(2)+'Cr';if(n>=1e5)return(n/1e5).toFixed(2)+'L';return n.toLocaleString('en-IN');}

function changeSensexExpiry(v){if(v)window.location.href=SENSEX_BASE+'?expiry='+encodeURIComponent(v);}

function toggleAutoRefresh(){
    G.autoOn=!G.autoOn;const btn=document.getElementById('autoRefreshBtn'),cnt=document.getElementById('refreshCountdown');
    if(G.autoOn){btn.textContent='ON';btn.style.cssText='background:#ea580c;color:white;font-weight:700;border-radius:99px;padding:4px 10px;font-size:10px;';cnt.classList.remove('hidden');_startAutoRefreshCycle();}
    else{btn.textContent='OFF';btn.style.cssText='';cnt.classList.add('hidden');clearInterval(G.autoTimer);}
}
function _startAutoRefreshCycle(){let s=15;const cnt=document.getElementById('refreshCountdown');clearInterval(G.autoTimer);G.autoTimer=setInterval(()=>{cnt.textContent=(--s)+'s';if(s<=0){clearInterval(G.autoTimer);_doChainRefresh();}},1000);}
async function _doChainRefresh(){
    if(!G.autoOn)return;
    try{const r=await fetch(REFRESH_URL+'?expiry='+encodeURIComponent(G.expiry),{headers:{'X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(!j.success)throw new Error(j.message);G.spot=j.sensexSpot;const sv=document.getElementById('sensexSpotValue');if(sv)sv.textContent=parseFloat(G.spot).toLocaleString('en-IN',{minimumFractionDigits:2});
    document.querySelectorAll('#chainBody tr[data-strike]').forEach(row=>{const d=j.data[parseInt(row.dataset.strike)];if(!d)return;['ce','pe'].forEach(t=>{if(!d[t])return;const lEl=row.querySelector(`[data-ltp="${t}"]`),cEl=row.querySelector(`[data-chg="${t}"]`);if(lEl){const v=parseFloat(d[t].ltp);lEl.textContent=v>0?v.toFixed(2):'—';lEl.classList.add('ltp-flash');setTimeout(()=>lEl.classList.remove('ltp-flash'),700);}if(cEl&&d[t].ltp>0){const c=d[t].percentChange;cEl.textContent=(c>=0?'▲':'▼')+Math.abs(c).toFixed(2)+'%';cEl.style.color=c>=0?'#22c55e':'#ef4444';}});});
    const lu=document.getElementById('lastUpdated');if(lu)lu.textContent='Updated: '+j.time;_calcPCR();}catch(e){console.warn('Refresh:',e.message);}
    if(G.autoOn)_startAutoRefreshCycle();
}

function _isMarketLive(){const ist=new Date(new Date().toLocaleString('en-US',{timeZone:'Asia/Kolkata'}));const hhmm=ist.getHours()*100+ist.getMinutes();return ist.getDay()>=1&&ist.getDay()<=5&&hhmm>=915&&hhmm<=1530;}

function startTickFeed(){stopTickFeed();if(!_isMarketLive()){_tickStatus('⬜ Market closed');return;}document.getElementById('tickSpeedWrap').style.display='flex';document.getElementById('sensexLiveChip').classList.remove('hidden');_tickStatus('🟢 Live feed ON ('+G.tickMs/1000+'s)');_doTick();G.tickTimer=setInterval(_doTick,G.tickMs);}
function stopTickFeed(){clearInterval(G.tickTimer);G.tickTimer=null;const b=document.getElementById('sxLiveLtpBadge');if(b)b.classList.add('hidden');}
function setTickSpeed(ms){G.tickMs=ms;_styleTick(ms);startTickFeed();}
async function _doTick(){
    if(!G.token||!G.series||!G.chart)return;
    try{const res=await fetch(`${LIVE_TICK_URL}?token=${encodeURIComponent(G.token)}&exchange=${G.exchange}&_t=${Date.now()}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const json=await res.json();if(!json.success)return;const ltp=parseFloat(json.tick?.ltp??0);if(!ltp||ltp<=0)return;
    const now=new Date(new Date().toLocaleString('en-US',{timeZone:'Asia/Kolkata'}));const mins={ONE_MINUTE:1,THREE_MINUTE:3,FIVE_MINUTE:5,FIFTEEN_MINUTE:15,THIRTY_MINUTE:30,ONE_HOUR:60}[G.interval]||5;
    const totalMin=now.getHours()*60+now.getMinutes();const bucket=Math.floor(totalMin/mins)*mins;const ts=Math.floor(new Date(now.getFullYear(),now.getMonth(),now.getDate(),Math.floor(bucket/60),bucket%60,0).getTime()/1000);
    if(G.currentCandle&&G.currentCandle.time===ts){G.currentCandle.high=Math.max(G.currentCandle.high,ltp);G.currentCandle.low=Math.min(G.currentCandle.low,ltp);G.currentCandle.close=ltp;}else{G.currentCandle={time:ts,open:ltp,high:ltp,low:ltp,close:ltp};}
    try{G.series.update(G.chartType==='candlestick'?G.currentCandle:{time:ts,value:ltp});}catch(_){}
    _updateTickUI(ltp);}catch(e){console.warn('Tick:',e.message);}
}
function _updateTickUI(ltp){
    const badge=document.getElementById('sxLiveLtpBadge');badge.classList.remove('hidden','ltp-flash-up','ltp-flash-dn');void badge.offsetWidth;if(G.lastLtp!==null)badge.classList.add(ltp>=G.lastLtp?'ltp-flash-up':'ltp-flash-dn');G.lastLtp=ltp;
    document.getElementById('sxLiveLtpVal').textContent=ltp.toFixed(2);document.getElementById('sensexModalTitle').textContent=`${G.label}  ●  ${ltp.toFixed(2)}`;
    if(G.currentCandle){document.getElementById('sxOhlcBar').classList.remove('hidden');document.getElementById('sxOhlcLabel').textContent='🟢 LIVE';document.getElementById('sxOVal').textContent=G.currentCandle.open.toFixed(2);document.getElementById('sxHVal').textContent=G.currentCandle.high.toFixed(2);document.getElementById('sxLVal').textContent=G.currentCandle.low.toFixed(2);document.getElementById('sxCVal').textContent=ltp.toFixed(2);const chg=G.currentCandle.open?(((ltp-G.currentCandle.open)/G.currentCandle.open)*100).toFixed(2):0;const tag=document.getElementById('sxChangeTag');tag.textContent=(chg>=0?'▲ +':'▼ ')+chg+'%';tag.style.color=chg>=0?'#22c55e':'#ef4444';}
    _tickStatus('🟢 '+new Date(new Date().toLocaleString('en-US',{timeZone:'Asia/Kolkata'})).toLocaleTimeString('en-IN',{hour12:false}));
}
function _tickStatus(msg){const el=document.getElementById('sxTickStatus');if(el)el.textContent=msg;}

function openSensexChart(token,label,exchange,otherToken,strike,side){
    G.token=token;G.exchange=exchange||'BFO';G.interval='FIVE_MINUTE';G.label=label;G.strike=strike;G.side=side;
    G.currentCandle=null;G.lastLtp=null;G.chartType='candlestick';G.tickMs=2000;
    G.modalChatHistory=[];G.modalChatTyping=false;G.sideChatTyping=false;G.aiAnalyzing=false;
    document.getElementById('sxMTyping')?.remove();document.getElementById('sxSTyping')?.remove();
    document.getElementById('sensexModalTitle').textContent=label;
    document.getElementById('sensexChartModal').style.display='flex';
    _sxResetAIPanel();document.getElementById('sxModalChatMsgs').innerHTML='';
    _addModalBubble(`<b>${label}</b> chart open hai — poochho kuch bhi! 📊`,'ai');
    _styleIv('FIVE_MINUTE');_styleTick(2000);_styleChartBtn('candlestick');_loadChart();
}
function closeSensexModal(){
    stopTickFeed();document.getElementById('sensexChartModal').style.display='none';document.getElementById('tickSpeedWrap').style.display='none';document.getElementById('sensexLiveChip').classList.add('hidden');
    if(G.chart){G.chart.remove();G.chart=null;G.series=null;}G.currentCandle=null;G.lastLtp=null;G.rawCandles=[];G.modalChatTyping=false;G.sideChatTyping=false;G.aiAnalyzing=false;document.getElementById('sxMTyping')?.remove();
}
function handleSensexBackdropClick(e){if(e.target.id==='sensexChartModal')closeSensexModal();}
function changeSensexInterval(iv){G.interval=iv;G.currentCandle=null;_styleIv(iv);_loadChart();}
function setSensexChartType(type){G.chartType=type;_styleChartBtn(type);_loadChart();}
function retrySensexLoad(){_loadChart();}

async function _loadChart(){
    if(!G.token)return;stopTickFeed();
    const loader=document.getElementById('sxChartLoader'),errBox=document.getElementById('sxChartError');
    loader.style.display='flex';errBox.style.display='none';document.getElementById('sxOhlcBar').classList.add('hidden');document.getElementById('sxLiveLtpBadge').classList.add('hidden');document.getElementById('sxCandleCount').textContent='Loading...';
    try{const res=await fetch(`${CANDLE_URL}?token=${encodeURIComponent(G.token)}&exchange=${encodeURIComponent(G.exchange)}&interval=${G.interval}&_t=${Date.now()}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const json=await res.json();if(!json.success||!json.data?.length)throw new Error(json.message||'Candle data nahi mila.');G.rawCandles=json.data;_buildChart(json.data);}
    catch(e){loader.style.display='none';errBox.style.display='flex';document.getElementById('sxErrMsg').textContent=e.message;document.getElementById('sxCandleCount').textContent='Error';}
}

// ✅ IST timestamp fix
function _toIST(ts){return new Date(ts*1000+((5.5*3600)-(new Date().getTimezoneOffset()*60))*0);}
function _istFmt(ts){
    // Robust IST conversion: ts is unix seconds UTC
    const d=new Date(ts*1000);
    const ist=new Date(d.toLocaleString('en-US',{timeZone:'Asia/Kolkata'}));
    return {
        dd:String(ist.getDate()).padStart(2,'0'),
        mon:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][ist.getMonth()],
        hh:String(ist.getHours()).padStart(2,'0'),
        mm:String(ist.getMinutes()).padStart(2,'0'),
        yr:ist.getFullYear()
    };
}

function _buildChart(raw){
    const container=document.getElementById('sensexChart');
    if(G.chart){G.chart.remove();G.chart=null;G.series=null;}

    G.chart=LightweightCharts.createChart(container,{
        width:container.clientWidth,height:container.clientHeight,
        layout:{background:{color:'#ffffff'},textColor:'#334155'},
        grid:{vertLines:{color:'#f1f5f9'},horzLines:{color:'#f1f5f9'}},
        crosshair:{mode:LightweightCharts.CrosshairMode.Normal},
        timeScale:{
            borderColor:'#e2e8f0',timeVisible:true,secondsVisible:false,
            // ✅ FIX: X-axis shows IST time, not UTC
            tickMarkFormatter:(ts,type)=>{
                const f=_istFmt(ts);
                return type<=2?`${f.dd} ${f.mon}`:`${f.hh}:${f.mm}`;
            }
        },
        rightPriceScale:{borderColor:'#e2e8f0'},
        localization:{
            // ✅ FIX: Crosshair tooltip shows IST
            timeFormatter:(ts)=>{const f=_istFmt(ts);return `${f.dd} ${f.mon} ${f.yr}  ${f.hh}:${f.mm} IST`;}
        },
    });

    new ResizeObserver(()=>{if(G.chart)G.chart.applyOptions({width:container.clientWidth,height:container.clientHeight});}).observe(container);

    // ✅ FIX: Parse Angel One timestamps — they return "+05:30" IST suffix
    const candles=raw.map(c=>{
        let ts;const r0=c[0];
        if(typeof r0==='number'){ts=r0;}
        else{let s=String(r0);ts=Math.floor(new Date(s.includes('+')||s.includes('Z')?s:s.replace(' ','T')+'+05:30').getTime()/1000);}
        return{time:ts,open:parseFloat(c[1]),high:parseFloat(c[2]),low:parseFloat(c[3]),close:parseFloat(c[4])};
    }).filter(c=>c.time>0&&c.open>0).sort((a,b)=>a.time-b.time);

    const map=new Map();candles.forEach(c=>map.set(c.time,c));
    const dedup=Array.from(map.values()).sort((a,b)=>a.time-b.time);

    if(!dedup.length){document.getElementById('sxChartLoader').style.display='none';document.getElementById('sxChartError').style.display='flex';document.getElementById('sxErrMsg').textContent='Candle data process nahi hua.';return;}

    if(G.chartType==='candlestick'){G.series=G.chart.addCandlestickSeries({upColor:'#22c55e',downColor:'#ef4444',borderUpColor:'#22c55e',borderDownColor:'#ef4444',wickUpColor:'#22c55e',wickDownColor:'#ef4444'});G.series.setData(dedup);}
    else{G.series=G.chart.addLineSeries({color:'#f97316',lineWidth:2});G.series.setData(dedup.map(c=>({time:c.time,value:c.close})));}

    G.chart.timeScale().fitContent();G.currentCandle={...dedup[dedup.length-1]};

    G.chart.subscribeCrosshairMove(param=>{
        if(!param.time||!param.seriesData)return;const d=param.seriesData.get(G.series);if(!d)return;
        const f=_istFmt(param.time);
        document.getElementById('sxOhlcBar').classList.remove('hidden');
        document.getElementById('sxOhlcLabel').textContent=`${f.dd} ${f.mon}  ${f.hh}:${f.mm} IST`;
        const o=d.open??d.value??0,h=d.high??d.value??0,l=d.low??d.value??0,cl=d.close??d.value??0;
        document.getElementById('sxOVal').textContent=o.toFixed(2);document.getElementById('sxHVal').textContent=h.toFixed(2);document.getElementById('sxLVal').textContent=l.toFixed(2);document.getElementById('sxCVal').textContent=cl.toFixed(2);
        const chg=o?(((cl-o)/o)*100).toFixed(2):0;const tag=document.getElementById('sxChangeTag');tag.textContent=(chg>=0?'▲ +':'▼ ')+chg+'%';tag.style.color=chg>=0?'#22c55e':'#ef4444';
    });

    document.getElementById('sxCandleCount').textContent=dedup.length+' candles • '+G.interval.replace('_',' ').toLowerCase();
    document.getElementById('sxChartLoader').style.display='none';
    startTickFeed();if(!G.aiAnalyzing)setTimeout(runSensexAiAnalyze,400);
}

function _sxResetAIPanel(){G.aiAnalyzing=false;_sxShow('sxAiWaiting');_sxHide('sxAiSkeleton');_sxHide('sxAiVerdictArea');const btn=document.getElementById('sxAnalyzeBtn'),txt=document.getElementById('sxAnalyzeBtnTxt');if(btn)Object.assign(btn.style,{opacity:'1',cursor:'pointer'});if(txt)txt.textContent='⚡ RE-ANALYZE';}
async function runSensexAiAnalyze(){
    if(G.aiAnalyzing)return;G.aiAnalyzing=true;_sxHide('sxAiWaiting');_sxHide('sxAiVerdictArea');_sxShow('sxAiSkeleton');
    const btn=document.getElementById('sxAnalyzeBtn'),txt=document.getElementById('sxAnalyzeBtnTxt');if(btn)Object.assign(btn.style,{opacity:'.5',cursor:'not-allowed'});if(txt)txt.textContent='⏳ Analyzing...';
    try{if(!G.rawCandles.length)throw new Error('Chart data load nahi hua.');
    const r=await fetch(SENSEX_AI_ANALYZE,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({label:G.label,strike:G.strike,side:G.side,spot:G.spot,expiry:G.expiry,pcr:document.getElementById('pcrValue')?.textContent||'—',interval:G.interval,candles:G.rawCandles.slice(-30)})});
    if(!r.ok)throw new Error('HTTP '+r.status);const j=await r.json();if(!j.success||!j.data)throw new Error(j.message||'No data');_renderSensexAI(j.data);}
    catch(e){_sxHide('sxAiSkeleton');_sxShow('sxAiWaiting');const wp=document.getElementById('sxAiWaiting')?.querySelector('p');if(wp)wp.innerHTML=`<span style="color:#dc2626;">❌ ${e.message}</span><br><small style="color:#64748b;">Retry karo.</small>`;}
    G.aiAnalyzing=false;if(btn)Object.assign(btn.style,{opacity:'1',cursor:'pointer'});if(txt)txt.textContent='🔄 Re-Analyze';
}
function _renderSensexAI(d){
    _sxHide('sxAiSkeleton');_sxShow('sxAiVerdictArea');
    document.getElementById('sxAiIcon').textContent=d.icon||'📊';document.getElementById('sxAiTitle').textContent=d.title||'--';document.getElementById('sxAiConfidence').textContent=d.confidence||'--';
    document.getElementById('sxAiVerdictBox').className=`flex items-center gap-3 p-3 rounded-lg border sx-av-${d.verdict||'neutral'}`;
    _sxSetM('sxAiTrend',d.trendAlign,d.trendAlignColor);_sxSetM('sxAiMomentum',d.momentum,d.momentumColor);_sxSetM('sxAiVol',d.volSig,d.volSigColor);_sxSetM('sxAiRisk',d.risk,d.riskColor);
    const lg=document.getElementById('sxAiLevelsGrid');
    if(d.keyLevels?.support||d.keyLevels?.resistance){if(d.keyLevels.support)document.getElementById('sxAiSupport').textContent=d.keyLevels.support;if(d.keyLevels.resistance)document.getElementById('sxAiResist').textContent=d.keyLevels.resistance;lg.style.display='';}else{lg.style.display='none';}
    const ts=document.getElementById('sxAiTimestamp');if(ts)ts.textContent='Updated: '+new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
function _sxSetM(id,val,color){const el=document.getElementById(id);if(!el)return;el.textContent=val||'--';el.style.color=(color&&color!=='#ffffff')?color:'#0f172a';}
function _sxShow(id){const e=document.getElementById(id);if(e){e.style.display='flex';}}
function _sxHide(id){const e=document.getElementById(id);if(e){e.style.display='none';}}

function toggleChat(){G.chatOpen=!G.chatOpen;const p=document.getElementById('chatPanel');p.classList.toggle('hidden',!G.chatOpen);p.style.display=G.chatOpen?'flex':'none';if(G.chatOpen)document.getElementById('chatInput').focus();}
function sendChip(t){document.getElementById('chatInput').value=t;sendChat();}
async function sendChat(){
    const inp=document.getElementById('chatInput');const msg=inp.value.trim();if(!msg)return;
    if(G.sideChatTyping){G.sideChatTyping=false;document.getElementById('sxSTyping')?.remove();}
    G.sideChatTyping=true;inp.value='';_addBubble(msg,'user','chatMessages');G.chatHistory.push({role:'user',content:msg});const tid=_addTyping('chatMessages');
    try{const r=await fetch(SENSEX_CHAT_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({message:msg,history:G.chatHistory.slice(-6),context:_chainCtx()})});const j=await r.json();_removeTyping(tid);const reply=j.reply||'Koi jawab nahi.';_addBubble(reply,'ai','chatMessages');G.chatHistory.push({role:'assistant',content:reply});}
    catch(e){_removeTyping(tid);_addBubble('❌ Error: '+e.message,'err','chatMessages');}finally{G.sideChatTyping=false;}
}
function _chainCtx(){let ce=0,pe=0,atm=null,minD=Infinity;const rows=[];document.querySelectorAll('#chainBody tr[data-strike]').forEach(r=>{const s=parseInt(r.dataset.strike);const cl=parseFloat(r.querySelector('[data-ltp="ce"]')?.textContent)||0;const pl=parseFloat(r.querySelector('[data-ltp="pe"]')?.textContent)||0;ce+=parseFloat(r.dataset.ceOi)||0;pe+=parseFloat(r.dataset.peOi)||0;const d=Math.abs(G.spot-s);if(d<minD){minD=d;atm=s;}if(cl>0||pl>0)rows.push(`Strike ${s}: CE=${cl} PE=${pl}`);});return `SENSEX Spot:${G.spot}|Expiry:${G.expiry}|ATM:${atm}|PCR:${ce>0?(pe/ce).toFixed(2):'N/A'}\n`+rows.slice(0,15).join('\n');}

function sendModalChip(t){document.getElementById('sxModalChatInput').value=t;sendModalChat();}
async function sendModalChat(){
    const inp=document.getElementById('sxModalChatInput');const msg=inp.value.trim();if(!msg)return;
    if(G.modalChatTyping){G.modalChatTyping=false;document.getElementById('sxMTyping')?.remove();}
    G.modalChatTyping=true;inp.value='';const dot=document.getElementById('sxChatStatusDot');const sendBtn=document.querySelector('#sensexChartModal button[onclick="sendModalChat()"]');
    if(dot){dot.textContent='● Thinking...';dot.style.color='#d97706';}if(sendBtn)sendBtn.style.opacity='0.5';
    _addModalBubble(msg,'user');G.modalChatHistory.push({role:'user',content:msg});const tid=_addModalTyping();
    try{const r=await fetch(SENSEX_CHART_CHAT,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({message:msg,label:G.label,strike:G.strike,side:G.side,context:{spot:G.spot,pcr:document.getElementById('pcrValue')?.textContent||'—',interval:G.interval,candles:G.rawCandles.slice(-15)}})});const j=await r.json();_removeModalTyping(tid);_addModalBubble(j.reply||'Koi jawab nahi.','ai');G.modalChatHistory.push({role:'assistant',content:j.reply||''});}
    catch(e){_removeModalTyping(tid);_addModalBubble('❌ Error: '+e.message,'err');}
    finally{G.modalChatTyping=false;if(dot){dot.textContent='● Ready';dot.style.color='#16a34a';}if(sendBtn)sendBtn.style.opacity='1';}
}

function _addModalBubble(t,type){const c=document.getElementById('sxModalChatMsgs');const d=document.createElement('div');d.className='msg-row flex '+(type==='user'?'justify-end':'justify-start');const cls=type==='user'?'modal-bubble-user':type==='err'?'modal-bubble-err':'modal-bubble-ai';d.innerHTML=`<div class="${cls}" style="max-width:88%;">${t.replace(/\n/g,'<br>')}</div>`;c.appendChild(d);c.scrollTop=c.scrollHeight;}
function _addModalTyping(){document.getElementById('sxMTyping')?.remove();const c=document.getElementById('sxModalChatMsgs');const d=document.createElement('div');d.id='sxMTyping';d.className='msg-row flex justify-start';d.innerHTML='<div class="modal-bubble-ai"><span class="animate-pulse">🤖 ...</span></div>';c.appendChild(d);c.scrollTop=c.scrollHeight;return 'sxMTyping';}
function _removeModalTyping(id){document.getElementById(id)?.remove();}
function _addBubble(t,type,cid){const c=document.getElementById(cid);const d=document.createElement('div');d.className='flex '+(type==='user'?'justify-end':'justify-start');d.innerHTML=`<div class="chat-bubble-${type==='err'?'err':type==='user'?'user':'ai'} px-3 py-2 text-[11px] leading-relaxed" style="max-width:88%;">${t.replace(/\n/g,'<br>')}</div>`;c.appendChild(d);c.scrollTop=c.scrollHeight;}
function _addTyping(cid){document.getElementById('sxSTyping')?.remove();const c=document.getElementById(cid);const d=document.createElement('div');d.id='sxSTyping';d.className='flex justify-start';d.innerHTML='<div class="chat-bubble-ai px-3 py-2 text-[11px]"><span class="animate-pulse">🤖 Soch raha hoon...</span></div>';c.appendChild(d);c.scrollTop=c.scrollHeight;return 'sxSTyping';}
function _removeTyping(id){document.getElementById(id)?.remove();}
</script>
</x-app-layout>
