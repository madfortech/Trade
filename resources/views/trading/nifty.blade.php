<x-app-layout>

<div class="nifty-terminal" style="background:#f1f5f9;min-height:100vh;padding:20px 24px;">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

{{-- TOP BAR --}}
<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0 16px;border-bottom:2px solid #e2e8f0;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:12px;">
        <flux:link href="{{ route('angel.option-data') }}" style="font-size:11px;font-weight:600;color:#3b82f6;text-decoration:none;padding:5px 12px;border-radius:6px;background:#eff6ff;border:1px solid #bfdbfe;">← Option Chain</flux:link>
        <span style="color:#cbd5e1;">|</span>
        <span style="font-size:12px;font-weight:700;color:#1e293b;letter-spacing:.12em;text-transform:uppercase;font-family:'JetBrains Mono',monospace;">NIFTY 50 — Live Terminal</span>
    </div>
    <span style="display:flex;align-items:center;gap:6px;font-size:10px;font-weight:700;color:#16a34a;background:#f0fdf4;border:1px solid #86efac;padding:4px 12px;border-radius:20px;font-family:'JetBrains Mono',monospace;">
        <span style="width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 6px #22c55e;display:inline-block;animation:pulse-dot 1.5s infinite;"></span> LIVE
    </span>
</div>

{{-- STATS BAR --}}
<div style="display:flex;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 20px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.04);">
    @foreach([
        ['label'=>'NIFTY 50',  'id'=>'nifty-ltp',    'color'=>'#1e293b',  'sub_id'=>'nifty-change'],
        ['label'=>'Day High',  'id'=>'nifty-high',   'color'=>'#16a34a'],
        ['label'=>'Day Low',   'id'=>'nifty-low',    'color'=>'#dc2626'],
        ['label'=>'Volume',    'id'=>'nifty-vol',    'color'=>'#2563eb'],
        ['label'=>'Interval',  'id'=>'current-interval-label', 'color'=>'#1e293b'],
    ] as $s)
        <div style="display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;">
            <span style="font-size:9px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#94a3b8;font-family:'JetBrains Mono',monospace;">{{ $s['label'] }}</span>
            <span id="{{ $s['id'] }}" style="font-size:18px;font-weight:700;color:{{ $s['color'] }};font-family:'JetBrains Mono',monospace;">--</span>
            @isset($s['sub_id'])<span id="{{ $s['sub_id'] }}" style="font-size:11px;font-weight:600;color:#64748b;">--</span>@endisset
        </div>
        @if(!$loop->last)<div style="width:1px;height:40px;background:#e2e8f0;margin:0 4px;"></div>@endif
    @endforeach
</div>

{{-- MAIN GRID --}}
<div style="display:grid;grid-template-columns:260px 1fr 240px;gap:14px;align-items:start;">

    {{-- ═══ LEFT: CHAT ═══ --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.04);display:flex;flex-direction:column;height:580px;">
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
            <span style="font-size:14px;">💬</span>
            <span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#475569;font-family:'JetBrains Mono',monospace;">AI Chat</span>
        </div>
        <div id="chat-messages" style="flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth;">
            <div class="nt-chat-welcome" style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:20px 10px;gap:10px;color:#94a3b8;font-size:11px;line-height:1.6;font-family:'DM Sans',sans-serif;">
                <div style="font-size:28px;">🤖</div>
                <p>Namaste! Main NIFTY trading expert hoon.<br>Kuch bhi poochho — analysis, entry, exit...</p>
            </div>
        </div>
        <div style="padding:10px 12px;border-top:1px solid #f1f5f9;display:flex;gap:8px;background:#f8fafc;">
            <input id="chat-input" type="text" placeholder="Message likhlo..." autocomplete="off"
                style="flex:1;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:11.5px;color:#1e293b;font-family:'DM Sans',sans-serif;outline:none;"/>
            <button id="chat-send" style="background:#3b82f6;color:#fff;border:none;border-radius:8px;padding:8px 12px;cursor:pointer;display:flex;align-items:center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>

    {{-- ═══ CENTER: CHART ═══ --}}
    <div style="background:#0d1526;border:1px solid #1a2740;border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.04);">
        {{-- Controls --}}
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid #1a2740;flex-wrap:wrap;gap:8px;">
            <div style="display:flex;gap:6px;">
                @foreach(['3m'=>'3M','5m'=>'5M','15m'=>'15M','1h'=>'1H','1d'=>'1D'] as $tf=>$lbl)
                    <button onclick="changeInterval('{{ $tf }}')"
                        class="nt-interval-btn {{ $tf==='5m'?'active':'' }}"
                        data-interval="{{ $tf }}"
                        style="{{ $tf==='5m' ? 'background:#1565a0;color:#c8e0f4;border-color:#1565a0;' : 'background:#091020;color:#4a6888;border:1px solid #1a2740;' }}padding:5px 12px;border-radius:6px;font-size:10px;font-weight:600;cursor:pointer;font-family:\'JetBrains Mono\',monospace;letter-spacing:.08em;border:1px solid transparent;">
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>
            <div style="display:flex;gap:6px;">
                <button id="btn-trendline" onclick="setTool('trendline')"
                    style="background:#091020;color:#4a6888;border:1px solid #1a2740;padding:5px 12px;border-radius:6px;font-size:10px;cursor:pointer;">
                    📈 Trend Line
                </button>
                <button onclick="clearTrendlines()"
                    style="background:#1a0a0a;color:#f87171;border:1px solid #3a1a1a;padding:5px 12px;border-radius:6px;font-size:10px;cursor:pointer;">
                    🗑 Clear
                </button>
            </div>
        </div>

        {{-- Chart --}}
        <div style="position:relative;height:440px;">
            <div id="nifty-chart" style="width:100%;height:100%;"></div>
            <div id="chart-overlay-loader" style="position:absolute;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;background:rgba(13,21,38,.85);backdrop-filter:blur(3px);z-index:10;gap:12px;">
                <div style="width:32px;height:32px;border-radius:50%;border:3px solid #1a2d4a;border-top-color:#3b82f6;animation:spin .8s linear infinite;"></div>
                <p id="overlay-loader-text" style="font-size:11px;color:#4a7aa8;font-family:'JetBrains Mono',monospace;">Fetching data...</p>
                <p id="overlay-loader-sub" style="font-size:9px;color:#2a4060;display:none;">Historical data load ho raha hai...</p>
            </div>
        </div>

        {{-- Footer --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 16px;border-top:1px solid #1a2740;">
            <div id="chart-loading" style="display:none;align-items:center;gap:6px;">
                <div style="width:10px;height:10px;border-radius:50%;border:2px solid #1a3060;border-top-color:#3b82f6;animation:spin .8s linear infinite;"></div>
                <span id="chart-loading-text" style="font-size:9px;color:#3b82f6;font-family:'JetBrains Mono',monospace;">Loading...</span>
            </div>
            <span id="chart-status" style="font-size:9px;color:#2a4060;font-family:'JetBrains Mono',monospace;">Tip: Trend Line → 2 points click karo</span>
            <span id="candle-info" style="font-size:9px;color:#4a7aa8;font-family:'JetBrains Mono',monospace;display:none;"></span>
        </div>
    </div>

    {{-- ═══ RIGHT: AI ANALYSIS ═══ --}}
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.04);display:flex;flex-direction:column;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
            <span style="font-size:14px;">🤖</span>
            <span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#475569;flex:1;font-family:'JetBrains Mono',monospace;">Live AI Analysis</span>
            <button onclick="triggerAIAnalysis()" id="ai-refresh-btn"
                style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;color:#3b82f6;padding:4px 7px;cursor:pointer;display:flex;align-items:center;"
                title="Refresh">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            </button>
        </div>

        {{-- Loading --}}
        <div id="ai-loading" style="display:none;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;gap:12px;">
            <div style="width:36px;height:36px;border-radius:50%;border:3px solid #e2e8f0;border-top-color:#3b82f6;animation:spin .8s linear infinite;"></div>
            <p style="font-size:11px;color:#64748b;font-family:'DM Sans',sans-serif;">Analyzing market...</p>
        </div>

        {{-- Error --}}
        <div id="ai-error" style="display:none;padding:20px;text-align:center;">
            <p id="ai-error-msg" style="font-size:11px;color:#dc2626;font-family:'DM Sans',sans-serif;margin-bottom:10px;">❌ Error occurred</p>
            <button onclick="triggerAIAnalysis()" style="background:#3b82f6;color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:11px;cursor:pointer;font-family:'DM Sans',sans-serif;">Retry</button>
        </div>

        {{-- Waiting --}}
        <div id="ai-waiting" style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:30px 16px;gap:12px;">
            <div style="font-size:30px;">📊</div>
            <p style="font-size:11px;color:#94a3b8;font-family:'DM Sans',sans-serif;line-height:1.6;">Chart load hone ke baad<br>analysis shuru hogi...</p>
            <button onclick="triggerAIAnalysis()" style="background:#3b82f6;color:#fff;border:none;border-radius:8px;padding:8px 20px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;">
                Analyze Now
            </button>
        </div>

        {{-- Result (shown after AI responds) --}}
        <div id="ai-result-content" style="display:none;flex-direction:column;">

            {{-- Verdict box --}}
            <div id="ai-verdict-box" style="display:flex;align-items:center;gap:12px;margin:14px;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;">
                <span id="ai-icon" style="font-size:22px;">📊</span>
                <div>
                    <p id="ai-title" style="font-size:12px;font-weight:700;color:#1e293b;letter-spacing:.04em;margin:0;">--</p>
                    <p id="ai-confidence" style="font-size:10px;color:#64748b;margin:2px 0 0;font-family:'DM Sans',sans-serif;line-height:1.4;">--</p>
                </div>
            </div>

            {{-- Metrics 2x3 grid --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:0 14px 10px;">
                <div class="ai-metric-box"><span class="ai-metric-lbl">Trend</span><span id="ai-trend" class="ai-metric-val">--</span></div>
                <div class="ai-metric-box"><span class="ai-metric-lbl">Momentum</span><span id="ai-momentum" class="ai-metric-val">--</span></div>
                <div class="ai-metric-box"><span class="ai-metric-lbl">Volume</span><span id="ai-vol-sig" class="ai-metric-val">--</span></div>
                <div class="ai-metric-box"><span class="ai-metric-lbl">Risk</span><span id="ai-risk" class="ai-metric-val">--</span></div>
                <div class="ai-metric-box" id="ai-support-row"><span class="ai-metric-lbl">Support</span><span id="ai-support" class="ai-metric-val" style="color:#16a34a;">--</span></div>
                <div class="ai-metric-box" id="ai-resist-row"><span class="ai-metric-lbl">Resistance</span><span id="ai-resist" class="ai-metric-val" style="color:#dc2626;">--</span></div>
            </div>

            {{-- Updated time --}}
            <p id="ai-updated" style="font-size:9px;color:#cbd5e1;text-align:center;padding:6px 8px 14px;font-family:'JetBrains Mono',monospace;"></p>
        </div>

    </div>{{-- /ai panel --}}

</div>{{-- /main grid --}}
</div>{{-- /nifty-terminal --}}

<style>
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.3} }
@keyframes spin { to{transform:rotate(360deg)} }
@keyframes fadeIn { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:translateY(0)} }

/* Chat scroll */
#chat-messages::-webkit-scrollbar { width:4px; }
#chat-messages::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:4px; }

/* Chat bubbles */
.nt-msg-user {
    align-self:flex-end; background:#3b82f6; color:#fff;
    padding:8px 12px; border-radius:12px 12px 2px 12px;
    font-size:11.5px; max-width:90%; line-height:1.5;
    font-family:'DM Sans',sans-serif; animation:fadeIn .2s ease;
}
.nt-msg-ai {
    align-self:flex-start; background:#f1f5f9; color:#1e293b;
    padding:8px 12px; border-radius:12px 12px 12px 2px;
    font-size:11.5px; max-width:95%; line-height:1.6;
    font-family:'DM Sans',sans-serif; border:1px solid #e2e8f0;
    animation:fadeIn .2s ease;
}

/* AI Metric boxes */
.ai-metric-box {
    display:flex; flex-direction:column; gap:3px;
    background:#f8fafc; border:1px solid #e2e8f0;
    border-radius:8px; padding:8px 10px;
    animation:fadeIn .3s ease;
}
.ai-metric-lbl { font-size:9px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#94a3b8; font-family:'JetBrains Mono',monospace; }
.ai-metric-val { font-size:11px; font-weight:600; color:#334155; font-family:'DM Sans',sans-serif; }

/* Interval btn active */
.nt-interval-btn.active { background:#1565a0!important; color:#c8e0f4!important; border-color:#1565a0!important; }

@media(max-width:1200px) {
    div[style*="grid-template-columns:260px"] { grid-template-columns:1fr!important; }
}
</style>

<script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>

<script>
// ── CSRF ──────────────────────────────────────────────────────────────────
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

// ── Chart loader hooks ────────────────────────────────────────────────────
window.showChartLoader = function(interval) {
    const msgs = {'3m':'Fetching 60 days...','5m':'Fetching 60 days...','15m':'Fetching 6 months...','1h':'Fetching 1 year...','1d':'Fetching 1 year...'};
    const isLong = ['15m','1h','1d'].includes(interval);

    const loader = document.getElementById('chart-overlay-loader');
    const loaderTxt = document.getElementById('overlay-loader-text');
    const loaderSub = document.getElementById('overlay-loader-sub');
    const topLoader = document.getElementById('chart-loading');
    const topTxt    = document.getElementById('chart-loading-text');

    if (loaderTxt) loaderTxt.textContent = msgs[interval] || 'Loading...';
    if (loaderSub) loaderSub.style.display = isLong ? 'block' : 'none';
    if (loader) loader.style.display = 'flex';
    if (topLoader) topLoader.style.display = 'flex';
    if (topTxt) topTxt.textContent = msgs[interval] || 'Loading...';

    // Update interval label
    const lbl = document.getElementById('current-interval-label');
    if (lbl) lbl.textContent = interval.toUpperCase();
};

window.hideChartLoader = function(candleCount) {
    const loader    = document.getElementById('chart-overlay-loader');
    const topLoader = document.getElementById('chart-loading');
    const info      = document.getElementById('candle-info');

    if (loader)    loader.style.display    = 'none';
    if (topLoader) topLoader.style.display = 'none';

    if (info && candleCount) {
        info.textContent   = candleCount + ' candles';
        info.style.display = 'inline';
    }

    // Auto-trigger AI after chart loads
    setTimeout(() => window.triggerAIAnalysis(), 1000);
};

// ── Interval button active state ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nt-interval-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.nt-interval-btn').forEach(b => {
                b.style.background  = '#091020';
                b.style.color       = '#4a6888';
                b.style.borderColor = '#1a2740';
            });
            btn.style.background  = '#1565a0';
            btn.style.color       = '#c8e0f4';
            btn.style.borderColor = '#1565a0';
        });
    });

    // Chat scroll observer
    const chatBox = document.getElementById('chat-messages');
    if (chatBox) {
        new MutationObserver(() => { chatBox.scrollTop = chatBox.scrollHeight; })
            .observe(chatBox, { childList: true, subtree: true });
    }
});

// ── AI Analysis ───────────────────────────────────────────────────────────
window.triggerAIAnalysis = async function() {
    // Hide all states
    document.getElementById('ai-waiting').style.display       = 'none';
    document.getElementById('ai-error').style.display         = 'none';
    document.getElementById('ai-result-content').style.display = 'none';
    document.getElementById('ai-loading').style.display        = 'flex';

    try {
        const interval = window._currentInterval || '5m';
        const candles  = (window._lastCandles || []).slice(-30);

        if (!candles.length) {
            throw new Error('Chart data abhi load nahi hua. Thoda wait karo.');
        }

        const res = await fetch('/angel/nifty-ai-analyze', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': _csrf,
            },
            body: JSON.stringify({ interval, candles }),
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const json = await res.json();

        document.getElementById('ai-loading').style.display = 'none';

        if (!json.success || !json.data) {
            throw new Error(json.message || 'Response data nahi mila');
        }

        renderAIResult(json.data);

    } catch(err) {
        document.getElementById('ai-loading').style.display   = 'none';
        document.getElementById('ai-error').style.display     = 'flex';
        document.getElementById('ai-error-msg').textContent   = '❌ ' + err.message;
        console.error('AI Analysis error:', err);
    }
};

function renderAIResult(d) {
    // Verdict
    document.getElementById('ai-icon').textContent       = d.icon       || '📊';
    document.getElementById('ai-title').textContent      = d.title      || '--';
    document.getElementById('ai-confidence').textContent = d.confidence || '--';

    // Verdict box color
    const box = document.getElementById('ai-verdict-box');
    const colors = {
        bullish: { bg:'#f0fdf4', border:'#86efac' },
        bearish: { bg:'#fef2f2', border:'#fca5a5' },
        neutral: { bg:'#fffbeb', border:'#fde68a' },
    };
    const c = colors[d.verdict] || colors.neutral;
    box.style.background  = c.bg;
    box.style.borderColor = c.border;

    // Metrics
    const setVal = (id, val, color) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val || '--';
        if (color) el.style.color = color;
    };
    setVal('ai-trend',    d.trendAlign,  d.trendAlignColor);
    setVal('ai-momentum', d.momentum,    d.momentumColor);
    setVal('ai-vol-sig',  d.volSig,      d.volSigColor);
    setVal('ai-risk',     d.risk,        d.riskColor);

    // Support / Resistance
    if (d.keyLevels?.support) {
        setVal('ai-support', d.keyLevels.support, '#16a34a');
        document.getElementById('ai-support-row').style.display = '';
    } else {
        document.getElementById('ai-support-row').style.display = 'none';
    }
    if (d.keyLevels?.resistance) {
        setVal('ai-resist', d.keyLevels.resistance, '#dc2626');
        document.getElementById('ai-resist-row').style.display = '';
    } else {
        document.getElementById('ai-resist-row').style.display = 'none';
    }

    // Updated time
    const now = new Date().toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('ai-updated').textContent = 'Updated: ' + now;

    // Show result
    document.getElementById('ai-result-content').style.display = 'flex';
}

// Auto-refresh every 5 min if result is showing
setInterval(() => {
    if (document.getElementById('ai-result-content').style.display !== 'none') {
        triggerAIAnalysis();
    }
}, 5 * 60 * 1000);
</script>

@vite(['resources/js/chart.js', 'resources/js/chatbox.js'])

</x-app-layout>
