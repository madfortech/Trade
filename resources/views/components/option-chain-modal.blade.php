<div id="chartModal" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center backdrop-blur-sm" onclick="handleBackdropClick(event)">
    <div id="modalBox" class="rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200" style="width:97vw;max-width:1460px;height:91vh;background:#f8fafc;">
        
        {{-- 1. HEADER --}}
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 flex-shrink-0 gap-3 flex-wrap" style="background:#ffffff;">
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
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                <div class="flex bg-gray-100 rounded overflow-hidden border border-gray-200">
                    <button onclick="setChartType('candlestick')" id="btnCandle"
                        class="text-[10px] px-2.5 py-1 font-bold text-white bg-indigo-600 transition-colors">▐ Candle</button>
                    <button onclick="setChartType('line')" id="btnLine"
                        class="text-[10px] px-2.5 py-1 font-bold text-gray-500 hover:text-gray-700 transition-colors border-l border-gray-200">⟋ Line</button>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 font-black text-xl leading-none ml-1 transition-colors">✕</button>
            </div>
        </div>

        {{-- 2. OHLC BAR --}}
        <div id="ohlcBar" style="display:none;" class="border-b border-gray-200 px-4 py-1.5 flex items-center gap-5 text-[10px] font-mono flex-shrink-0 flex-wrap"
             style="background:#f1f5f9;color:#475569;">
            <span class="text-indigo-600 font-bold" id="ohlcLabel"></span>
            <span>O <strong id="oVal" class="text-gray-800">—</strong></span>
            <span>H <strong id="hVal" class="text-green-700">—</strong></span>
            <span>L <strong id="lVal" class="text-red-600">—</strong></span>
            <span>C <strong id="cVal" class="text-gray-800">—</strong></span>
            <span>Vol <strong id="volVal" class="text-amber-700">—</strong></span>
            <strong id="changeTag"></strong>
        </div>

        {{-- 3. MAIN BODY --}}
        <div class="flex flex-1 overflow-hidden min-h-0">
            {{-- LEFT: CHART --}}
            <div class="relative flex-1 overflow-hidden border-r border-gray-200 min-w-0" style="background:#ffffff;">
                <div id="mainChart" class="absolute inset-0"></div>
                <div id="chartLoader" class="absolute inset-0 flex flex-col items-center justify-center z-20" style="background:#f8fafc;">
                    <div class="w-10 h-10 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin mb-3"></div>
                    <p class="text-gray-500 text-[11px] uppercase tracking-widest">Fetching Data...</p>
                </div>
                <div id="chartError" class="hidden absolute inset-0 flex flex-col items-center justify-center z-20" style="background:#f8fafc;">
                    <p id="errMsg" class="text-red-600 text-sm text-center px-4"></p>
                </div>
            </div>

            {{-- RIGHT PANEL: AI & CHAT --}}
            <div class="flex flex-col border-l border-gray-100 shadow-inner" style="width:340px;min-width:300px;max-width:340px;flex-shrink:0;background:#ffffff;">
                
                {{-- AI ANALYSIS PANEL --}}
                <div class="flex-shrink-0 border-b border-gray-200 flex flex-col" style="height:50%;">
                    <div class="flex items-center gap-2 px-3 py-2.5 border-b border-gray-200" style="background:#f8fafc;">
                        <span class="text-[10px] font-black uppercase tracking-widest flex-1 text-slate-600">Live AI Analysis</span>
                        <button id="aiAnalyzeBtn" onclick="runAIAnalysis()"
                            class="text-[9px] font-black px-3 py-1.5 rounded-full bg-indigo-600 text-white uppercase tracking-wide shadow-sm hover:bg-indigo-700 active:scale-95 transition-all">
                            <span id="aiAnalyzeBtnTxt">⚡ Analyze</span>
                        </button>
                    </div>

                    <div id="aiResultArea" class="flex-1 overflow-y-auto bg-white">

                        {{-- Waiting State --}}
                        <div id="aiWaiting" style="display:flex;" class="p-8 text-center flex-col items-center justify-center h-full gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center">
                                <span class="animate-bounce">🤖</span>
                            </div>
                            <p class="text-[11px] text-gray-500 leading-relaxed">Chart load hone ke baad<br><strong class="text-indigo-600">AI Analysis</strong> trigger hogi...</p>
                        </div>

                        {{-- Loading Skeleton --}}
                        <div id="aiSkeleton" style="display:none;" class="p-4 space-y-4">
                            <div class="h-8 bg-gray-100 animate-pulse rounded-lg w-full"></div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="h-12 bg-gray-50 animate-pulse rounded-md"></div>
                                <div class="h-12 bg-gray-50 animate-pulse rounded-md"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="h-3 bg-gray-100 animate-pulse rounded w-3/4"></div>
                                <div class="h-3 bg-gray-100 animate-pulse rounded w-1/2"></div>
                            </div>
                        </div>

                        {{-- Actual Verdict Result --}}
                        <div id="aiVerdictArea" style="display:none;" class="p-4">
                            <div id="aiVerdictBox" class="flex items-center gap-3 p-3 rounded-xl border bg-white shadow-sm mb-4">
                                <span id="aiIcon" class="text-2xl"></span>
                                <div class="flex flex-col">
                                    <span id="aiVerdictTitle" class="font-black text-sm uppercase tracking-tight text-slate-800"></span>
                                    <span id="aiConf" class="text-[9px] font-bold text-slate-400"></span>
                                </div>
                            </div>

                            <div id="aiLevelsGrid" class="grid grid-cols-2 gap-2 mb-4">
                                <div class="p-2.5 rounded-lg bg-green-50 border border-green-100 text-center">
                                    <div class="text-[9px] uppercase font-bold text-green-600 mb-0.5">Support</div>
                                    <div id="aiSupport" class="font-mono font-bold text-green-700 text-xs"></div>
                                </div>
                                <div class="p-2.5 rounded-lg bg-red-50 border border-red-100 text-center">
                                    <div class="text-[9px] uppercase font-bold text-red-600 mb-0.5">Resistance</div>
                                    <div id="aiResist" class="font-mono font-bold text-red-700 text-xs"></div>
                                </div>
                            </div>

                            <div class="space-y-2.5 text-[11px] border-t border-gray-100 pt-4">
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>Trend Alignment</span>
                                    <b id="aiTrendAlign" class="font-black"></b>
                                </div>
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>Momentum Strength</span>
                                    <b id="aiMomentum" class="font-black"></b>
                                </div>
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>Volume Signal</span>
                                    <b id="aiVolSig" class="font-black"></b>
                                </div>
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>Risk Factor</span>
                                    <b id="aiRisk" class="font-black"></b>
                                </div>
                            </div>
                            <div id="aiTimestamp" class="text-[8px] text-gray-300 mt-6 text-right font-mono uppercase"></div>
                        </div>
                    </div>
                </div>

                {{-- CHAT PANEL --}}
                <div class="flex flex-col flex-1 min-h-0 bg-slate-50/30">
                    <div class="px-3 py-1.5 border-b border-gray-100 flex items-center justify-between">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">AI Consultant</span>
                        <span id="chatStatusDot" class="text-[8px] font-black uppercase" style="color:#16a34a;">● Ready</span>
                    </div>
                    <div id="chatMessages" class="flex-1 overflow-y-auto flex flex-col gap-3 p-3 scroll-smooth"></div>
                    
                    <div class="p-3 bg-white border-t border-gray-200">
                        <div class="relative flex items-end gap-2 bg-gray-50 rounded-xl border border-gray-200 p-1.5 focus-within:border-indigo-400 transition-all">
                            <textarea id="chatInput" 
                                      onkeydown="handleChatKey(event)" 
                                      oninput="chatAutoResize(this)"
                                      rows="1" 
                                      placeholder="Poochiye trade ke baare mein..."
                                      class="flex-1 bg-transparent border-none focus:ring-0 text-[11px] py-1.5 px-2 resize-none max-h-24"></textarea>
                            <button id="chatSendBtn" onclick="sendChat()" 
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. FOOTER --}}
        <div class="border-t border-gray-200 px-4 py-1.5 flex justify-between items-center flex-shrink-0" style="background:#f1f5f9;">
            <span id="candleCount" class="text-[10px] font-mono text-gray-500 tracking-tighter">—</span>
            <div class="flex items-center gap-3 text-[10px]">
                <span class="text-indigo-500 font-bold tracking-widest">ANGEL ONE DATA ENGINE • GEMINI AI 2.0</span>
            </div>
        </div>
    </div>
</div>