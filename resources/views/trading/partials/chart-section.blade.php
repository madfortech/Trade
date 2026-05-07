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
        {{-- YE DIV SABSE IMPORTANT HAI --}}
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