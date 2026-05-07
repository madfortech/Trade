<div style="display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px 20px; margin-bottom:16px; box-shadow:0 1px 4px rgba(0,0,0,.04);">
    @php
        $stats = [
            ['label'=>'NIFTY 50', 'id'=>'nifty-ltp', 'color'=>'#1e293b', 'sub_id'=>'nifty-change'],
            ['label'=>'Day High', 'id'=>'nifty-high', 'color'=>'#16a34a'],
            ['label'=>'Day Low',  'id'=>'nifty-low',  'color'=>'#dc2626'],
       
            ['label'=>'Interval', 'id'=>'current-interval-label', 'color'=>'#7c3aed'],
        ];
    @endphp

    @foreach($stats as $s)
        <div style="display:flex; flex-direction:column; align-items:center; gap:3px; flex:1;">
            <span style="font-size:9px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:#94a3b8; font-family:'JetBrains Mono',monospace;">{{ $s['label'] }}</span>
            <span id="{{ $s['id'] }}" style="font-size:18px; font-weight:700; color:{{ $s['color'] }}; font-family:'JetBrains Mono',monospace;">--</span>
            @isset($s['sub_id'])<span id="{{ $s['sub_id'] }}" style="font-size:11px; font-weight:600; color:#64748b;">--</span>@endisset
        </div>
        @if(!$loop->last)<div style="width:1px; height:40px; background:#e2e8f0; margin:0 4px;"></div>@endif
    @endforeach
</div>