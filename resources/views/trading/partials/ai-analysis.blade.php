<div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.04); display:flex; flex-direction:column;">
    {{-- Header --}}
    <div style="display:flex; align-items:center; gap:8px; padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <span style="font-size:14px;">🤖</span>
        <span style="font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#475569; flex:1; font-family:'JetBrains Mono',monospace;">AI Analysis</span>
        <button onclick="triggerAIAnalysis()" id="ai-refresh-btn" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; color:#3b82f6; padding:4px 7px; cursor:pointer;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="23 4 23 10 17 10"/>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
        </button>
    </div>

    {{-- Loading State --}}
    <div id="ai-loading" style="display:none; flex-direction:column; align-items:center; justify-content:center; padding:40px 20px; gap:12px;">
        <div class="loader-spin-blue"></div>
        <p style="font-size:11px; color:#64748b;">Analyzing Market...</p>
    </div>

    {{-- Initial State --}}
    <div id="ai-waiting" style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:30px 16px; gap:12px;">
        <div style="font-size:30px;">📊</div>
        <p style="font-size:11px; color:#94a3b8; line-height:1.6;">Click 'Analyze Now' for<br>latest Nifty insights.</p>
        <button onclick="triggerAIAnalysis()" style="background:#3b82f6; color:#fff; border:none; border-radius:8px; padding:8px 20px; font-size:11px; font-weight:600; cursor:pointer;">Analyze Now</button>
    </div>

    {{-- Result Content (Hidden by default) --}}
    <div id="ai-result-content" style="display:none; flex-direction:column;">
        <div id="ai-verdict-box" style="margin:14px; padding:12px 14px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:12px;">
            <span id="ai-icon" style="font-size:22px;">📊</span>
            <div>
                <p id="ai-title" style="font-size:12px; font-weight:700; color:#1e293b; margin:0;">--</p>
                <p id="ai-confidence" style="font-size:10px; color:#64748b; margin:2px 0 0;">--</p>
            </div>
        </div>

        {{-- Metrics Grid --}}
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