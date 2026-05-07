<x-app-layout>
    <div class="nifty-terminal" style="background:#f1f5f9; min-height:100vh; padding:20px 24px;">
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

        {{-- TOP BAR --}}
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0 16px; border-bottom:2px solid #e2e8f0; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <a href="{{ route('angel.option-data') }}" style="font-size:11px; font-weight:600; color:#3b82f6; text-decoration:none; padding:5px 12px; border-radius:6px; background:#eff6ff; border:1px solid #bfdbfe;">← Option Chain</a>
                <span style="color:#cbd5e1;">|</span>
                <span style="font-size:12px; font-weight:700; color:#1e293b; letter-spacing:.12em; text-transform:uppercase; font-family:'JetBrains Mono',monospace;">NIFTY 50 — Live Terminal</span>
            </div>
            <span style="display:flex; align-items:center; gap:6px; font-size:10px; font-weight:700; color:#16a34a; background:#f0fdf4; border:1px solid #86efac; padding:4px 12px; border-radius:20px; font-family:'JetBrains Mono',monospace;">
                <span style="width:7px; height:7px; border-radius:50%; background:#22c55e; box-shadow:0 0 6px #22c55e; display:inline-block; animation:pulse-dot 1.5s infinite;"></span> LIVE
            </span>
        </div>

        {{-- STATS BAR --}}
        <div style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px 20px; margin-bottom:16px; box-shadow:0 1px 4px rgba(0,0,0,.04);">
            @foreach([
                ['label'=>'NIFTY 50', 'id'=>'nifty-ltp', 'color'=>'#1e293b', 'sub_id'=>'nifty-change'],
                ['label'=>'Day High', 'id'=>'nifty-high', 'color'=>'#16a34a'],
                ['label'=>'Day Low',  'id'=>'nifty-low',  'color'=>'#dc2626'],
                ['label'=>'Volume',   'id'=>'nifty-vol',   'color'=>'#2563eb'],
                ['label'=>'Interval', 'id'=>'current-interval-label', 'color'=>'#7c3aed'],
            ] as $s)
                <div style="display:flex; flex-direction:column; align-items:center; gap:3px; flex:1;">
                    <span style="font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#94a3b8; font-family:'JetBrains Mono',monospace;">{{ $s['label'] }}</span>
                    <span id="{{ $s['id'] }}" style="font-size:18px; font-weight:700; color:{{ $s['color'] }}; font-family:'JetBrains Mono',monospace;">--</span>
                    @isset($s['sub_id'])<span id="{{ $s['sub_id'] }}" style="font-size:11px; font-weight:600; color:#64748b;">--</span>@endisset
                </div>
                @if(!$loop->last)<div style="width:1px; height:40px; background:#e2e8f0; margin:0 4px;"></div>@endif
            @endforeach
        </div>

        {{-- MAIN GRID --}}
        <div style="display:grid; grid-template-columns:260px 1fr 240px; gap:14px; align-items:start;">

            {{-- LEFT: CHAT --}}
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.04); display:flex; flex-direction:column; height:580px;">
                <div style="display:flex; align-items:center; gap:8px; padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                    <span style="font-size:14px;">💬</span>
                    <span style="font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#475569; font-family:'JetBrains Mono',monospace;">AI Chat</span>
                </div>
                <div id="chat-messages" style="flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px;">
                    <div class="nt-chat-welcome" style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:20px 10px; gap:10px; color:#94a3b8; font-size:11px;">
                        <div style="font-size:28px;">🤖</div>
                        <p>Namaste! Main NIFTY trading expert hoon.<br>Analysis ya entry/exit ke baare mein poochein.</p>
                    </div>
                </div>
                <div style="padding:10px 12px; border-top:1px solid #f1f5f9; display:flex; gap:8px; background:#f8fafc;">
                    <input id="chat-input" type="text" placeholder="Poochiye..." style="flex:1; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; font-size:11.5px; outline:none;"/>
                    <button id="chat-send" style="background:#3b82f6; color:#fff; border:none; border-radius:8px; padding:8px 12px; cursor:pointer;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 2 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>

            {{-- CENTER: CHART --}}
            <div style="background:#0d1526; border:1px solid #1a2740; border-radius:14px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.04);">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 16px; border-bottom:1px solid #1a2740;">
                    <div style="display:flex; gap:6px;">
                        @foreach(['3m'=>'3M','5m'=>'5M', '15m'=>'15M', '1h'=>'1H','1d'=>'1D'] as $tf=>$lbl)
                            <button onclick="changeInterval('{{ $tf }}')"
                                class="nt-interval-btn {{ $tf==='5m'?'active':'' }}"
                                data-interval="{{ $tf }}"
                                style="background:#091020; color:#4a6888; border:1px solid #1a2740; padding:5px 12px; border-radius:6px; font-size:10px; font-weight:600; cursor:pointer; font-family:'JetBrains Mono',monospace;">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button id="btn-trendline" onclick="setTool('trendline')" style="background:#091020; color:#4a6888; border:1px solid #1a2740; padding:5px 12px; border-radius:6px; font-size:10px; cursor:pointer;">📈 Trend Line</button>
                        <button onclick="clearTrendlines()" style="background:#1a0a0a; color:#f87171; border:1px solid #3a1a1a; padding:5px 12px; border-radius:6px; font-size:10px; cursor:pointer;">🗑 Clear</button>
                    </div>
                </div>

                <div style="position:relative; height:440px;">
                    <div id="nifty-chart" style="width:100%; height:100%;"></div>
                    <div id="chart-overlay-loader" style="position:absolute; inset:0; display:none; flex-direction:column; align-items:center; justify-content:center; background:rgba(13,21,38,.88); z-index:10; gap:12px;">
                        <div class="loader-spin"></div>
                        <p id="overlay-loader-text" style="font-size:11px; color:#4a7aa8; font-family:'JetBrains Mono',monospace;">Fetching data...</p>
                    </div>
                </div>

                <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 16px; border-top:1px solid #1a2740;">
                    <span id="chart-status" style="font-size:9px; color:#2a4060; font-family:'JetBrains Mono',monospace;">Market Hours: 9:15 AM - 3:30 PM</span>
                    <span id="candle-info" style="font-size:9px; color:#4a7aa8; font-family:'JetBrains Mono',monospace;"></span>
                </div>
            </div>

            {{-- RIGHT: AI ANALYSIS --}}
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.04); display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:8px; padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                    <span style="font-size:14px;">🤖</span>
                    <span style="font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#475569; flex:1; font-family:'JetBrains Mono',monospace;">AI Analysis</span>
                    <button onclick="triggerAIAnalysis()" id="ai-refresh-btn" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; color:#3b82f6; padding:4px 7px; cursor:pointer;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </button>
                </div>

                <div id="ai-loading" style="display:none; flex-direction:column; align-items:center; justify-content:center; padding:40px 20px; gap:12px;">
                    <div class="loader-spin-blue"></div>
                    <p style="font-size:11px; color:#64748b;">Analyzing Market...</p>
                </div>

                <div id="ai-waiting" style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:30px 16px; gap:12px;">
                    <div style="font-size:30px;">📊</div>
                    <p style="font-size:11px; color:#94a3b8; line-height:1.6;">Click 'Analyze Now' for<br>latest Nifty insights.</p>
                    <button onclick="triggerAIAnalysis()" style="background:#3b82f6; color:#fff; border:none; border-radius:8px; padding:8px 20px; font-size:11px; font-weight:600; cursor:pointer;">Analyze Now</button>
                </div>

                <div id="ai-result-content" style="display:none; flex-direction:column;">
                    <div id="ai-verdict-box" style="margin:14px; padding:12px 14px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:12px;">
                        <span id="ai-icon" style="font-size:22px;">📊</span>
                        <div>
                            <p id="ai-title" style="font-size:12px; font-weight:700; color:#1e293b; margin:0;">--</p>
                            <p id="ai-confidence" style="font-size:10px; color:#64748b; margin:2px 0 0;">--</p>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; padding:0 14px 10px;">
                        <div class="ai-metric-box"><span class="ai-metric-lbl">Trend</span><span id="ai-trend" class="ai-metric-val">--</span></div>
                        <div class="ai-metric-box"><span class="ai-metric-lbl">Momentum</span><span id="ai-momentum" class="ai-metric-val">--</span></div>
                        <div class="ai-metric-box"><span class="ai-metric-lbl">Volume</span><span id="ai-vol-sig" class="ai-metric-val">--</span></div>
                        <div class="ai-metric-box"><span class="ai-metric-lbl">Risk</span><span id="ai-risk" class="ai-metric-val">--</span></div>
                        <div class="ai-metric-box"><span class="ai-metric-lbl">Support</span><span id="ai-support" class="ai-metric-val text-green-600">--</span></div>
                        <div class="ai-metric-box"><span class="ai-metric-lbl">Resist</span><span id="ai-resist" class="ai-metric-val text-red-600">--</span></div>
                    </div>
                    <p id="ai-updated" style="font-size:9px; color:#cbd5e1; text-align:center; padding-bottom:14px; font-family:'JetBrains Mono',monospace;"></p>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.3} }
        .loader-spin { width:32px; height:32px; border-radius:50%; border:3px solid #1a2d4a; border-top-color:#3b82f6; animation:spin .8s linear infinite; }
        .loader-spin-blue { width:32px; height:32px; border-radius:50%; border:3px solid #e2e8f0; border-top-color:#3b82f6; animation:spin .8s linear infinite; }
        @keyframes spin { to{transform:rotate(360deg)} }
        .nt-interval-btn.active { background:#1565a0!important; color:#fff!important; border-color:#3b82f6!important; }
        .ai-metric-box { display:flex; flex-direction:column; gap:3px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; }
        .ai-metric-lbl { font-size:9px; font-weight:600; color:#94a3b8; text-transform:uppercase; font-family:'JetBrains Mono',monospace; }
        .ai-metric-val { font-size:11px; font-weight:600; color:#334155; }
    </style>

    <script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>

    <script>
        // (function () {
        //     const _intervalLabels = { '3m':'3 MIN', '5m':'5 MIN', '15m':'15 MIN', '1h':'1 HR', '1d':'1 DAY' };

        //     // --- 1. Loaders Logic ---
        //     window.showChartLoader = (interval) => {
        //         const loader = document.getElementById('chart-overlay-loader');
        //         if (loader) loader.style.display = 'flex';
        //         document.getElementById('overlay-loader-text').textContent = 'Loading ' + (_intervalLabels[interval] || interval) + '...';
        //         const lbl = document.getElementById('current-interval-label');
        //         if (lbl) lbl.textContent = _intervalLabels[interval] || interval;
        //     };

        //     window.hideChartLoader = (count) => {
        //         const loader = document.getElementById('chart-overlay-loader');
        //         if (loader) loader.style.display = 'none';
        //         if (count > 0) document.getElementById('candle-info').textContent = count + ' candles loaded';
        //     };

        //     // --- 2. Live Stats Updater (LTP, High, Low, Vol) ---
        //     async function refreshNiftyLiveStats() {
        //         try {
        //             const res = await fetch('/angel/nifty/ltp');
        //             const data = await res.json();

        //             if (data.success) {
        //                 // LTP & Change
        //                 document.getElementById('nifty-ltp').textContent = data.ltp.toFixed(2);
        //                 const changeEl = document.getElementById('nifty-change');
        //                 if (changeEl) {
        //                     const sign = data.change >= 0 ? '+' : '';
        //                     changeEl.textContent = `${sign}${data.change.toFixed(2)} (${data.percent.toFixed(2)}%)`;
        //                     changeEl.style.color = data.change >= 0 ? '#16a34a' : '#dc2626';
        //                 }
        //                 // High/Low
        //                 document.getElementById('nifty-high').textContent = data.high.toFixed(2);
        //                 document.getElementById('nifty-low').textContent = data.low.toFixed(2);
        //                 // Volume
        //                 let vol = data.volume;
        //                 document.getElementById('nifty-vol').textContent = vol >= 10000000 ? (vol/10000000).toFixed(2) + ' Cr' : (vol/100000).toFixed(2) + ' L';
        //             }
        //         } catch (e) {
        //             console.warn("Stats refresh pending...");
        //         }
        //     }

        //     // --- 3. AI Analysis Logic ---
        //     window.triggerAIAnalysis = async function () {
        //         const loading = document.getElementById('ai-loading');
        //         const result = document.getElementById('ai-result-content');
        //         const waiting = document.getElementById('ai-waiting');
                
        //         loading.style.display = 'flex';
        //         result.style.display = 'none';
        //         waiting.style.display = 'none';

        //         try {
        //             const candles = (window._lastCandles || []).slice(-30);
        //             const res = await fetch('/angel/nifty-ai-analyze', {
        //                 method: 'POST',
        //                 headers: { 
        //                     'Content-Type': 'application/json',
        //                     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
        //                 },
        //                 body: JSON.stringify({ interval: window._currentInterval, candles })
        //             });

        //             const json = await res.json();
        //             if (json.success) renderAI(json.data);
        //             else waiting.style.display = 'flex';
        //         } catch (e) {
        //             waiting.style.display = 'flex';
        //         } finally {
        //             loading.style.display = 'none';
        //         }
        //     };

        //     function renderAI(d) {
        //         document.getElementById('ai-result-content').style.display = 'flex';
        //         document.getElementById('ai-icon').textContent = d.icon;
        //         document.getElementById('ai-title').textContent = d.title;
        //         document.getElementById('ai-confidence').textContent = d.confidence;
        //         document.getElementById('ai-trend').textContent = d.trendAlign;
        //         document.getElementById('ai-momentum').textContent = d.momentum;
        //         document.getElementById('ai-vol-sig').textContent = d.volSig;
        //         document.getElementById('ai-risk').textContent = d.risk;
        //         document.getElementById('ai-support').textContent = d.keyLevels.support;
        //         document.getElementById('ai-resist').textContent = d.keyLevels.resistance;
        //         document.getElementById('ai-updated').textContent = 'Last sync: ' + new Date().toLocaleTimeString();

        //         const box = document.getElementById('ai-verdict-box');
        //         const colors = { bullish: ['#f0fdf4', '#86efac'], bearish: ['#fff1f2', '#fecaca'], sideways: ['#fffbeb', '#fde68a'] };
        //         if (colors[d.verdict]) {
        //             box.style.background = colors[d.verdict][0];
        //             box.style.borderColor = colors[d.verdict][1];
        //         }
        //     }

        //     // Start Intervals
        //     setInterval(refreshNiftyLiveStats, 2000);
        //     document.addEventListener('DOMContentLoaded', refreshNiftyLiveStats);
        // })();
    </script>

    @vite(['resources/js/chart.js', 'resources/js/chatbox.js'])
</x-app-layout>