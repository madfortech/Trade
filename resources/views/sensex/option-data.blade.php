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
window.SENSEX_SELECTED_EXPIRY = @json($selectedExpiry);
window.SENSEX_SPOT = @json($sensexSpot ?? 0);
</script>
@vite(['resources/js/sensex-option-data.js'])
</x-app-layout>
