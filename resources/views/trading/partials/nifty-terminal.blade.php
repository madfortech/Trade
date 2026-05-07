<x-app-layout>
    <div class="nifty-terminal" style="background:#f1f5f9; min-height:100vh; padding:20px 24px;">
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

        {{-- 1. Top Navigation Bar --}}
        @include('trading.partials.top-bar')

        {{-- 2. Stats Bar (LTP, High, Low) --}}
        @include('trading.partials.stats-bar')

        {{-- Main Grid --}}
        <div style="display:grid; grid-template-columns:260px 1fr 240px; gap:14px; align-items:start;">
            
            {{-- 3. Left Chat Box --}}
            @include('trading.partials.chat-box')

            {{-- 4. Center Chart Section --}}
            @include('trading.partials.chart-section')

            {{-- 5. Right AI Analysis --}}
            @include('trading.partials.ai-analysis')

        </div>
    </div>

    {{-- 6. All Page Scripts --}}
    @include('trading.partials.scripts')
</x-app-layout>