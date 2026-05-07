<style>
    @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.3} }
    .loader-spin { width:32px; height:32px; border-radius:50%; border:3px solid #1a2d4a; border-top-color:#3b82f6; animation:spin .8s linear infinite; }
    .loader-spin-blue { width:32px; height:32px; border-radius:50%; border:3px solid #e2e8f0; border-top-color:#3b82f6; animation:spin .8s linear infinite; }
    @keyframes spin { to{transform:rotate(360deg)} }
    .nt-interval-btn.active { background:#1565a0!important; color:#fff!important; border-color:#3b82f6!important; }
    .ai-metric-box { display:flex; flex-direction:column; gap:3px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; }
</style>

<script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>

<script>
     (function () {
            const _intervalLabels = { '3m':'3 MIN', '5m':'5 MIN', '15m':'15 MIN', '1h':'1 HR', '1d':'1 DAY' };

            // --- 1. Loaders Logic ---
            window.showChartLoader = (interval) => {
                const loader = document.getElementById('chart-overlay-loader');
                if (loader) loader.style.display = 'flex';
                document.getElementById('overlay-loader-text').textContent = 'Loading ' + (_intervalLabels[interval] || interval) + '...';
                const lbl = document.getElementById('current-interval-label');
                if (lbl) lbl.textContent = _intervalLabels[interval] || interval;
            };

            window.hideChartLoader = (count) => {
                const loader = document.getElementById('chart-overlay-loader');
                if (loader) loader.style.display = 'none';
                if (count > 0) document.getElementById('candle-info').textContent = count + ' candles loaded';
            };

            // --- 2. Live Stats Updater (LTP, High, Low, Vol) ---
            async function refreshNiftyLiveStats() {
                try {
                    const res = await fetch('/angel/nifty/ltp');
                    const data = await res.json();

                    if (data.success) {
                        // LTP & Change
                        document.getElementById('nifty-ltp').textContent = data.ltp.toFixed(2);
                        const changeEl = document.getElementById('nifty-change');
                        if (changeEl) {
                            const sign = data.change >= 0 ? '+' : '';
                            changeEl.textContent = `${sign}${data.change.toFixed(2)} (${data.percent.toFixed(2)}%)`;
                            changeEl.style.color = data.change >= 0 ? '#16a34a' : '#dc2626';
                        }
                        // High/Low
                        document.getElementById('nifty-high').textContent = data.high.toFixed(2);
                        document.getElementById('nifty-low').textContent = data.low.toFixed(2);
                        // Volume
                        let vol = data.volume;
                        document.getElementById('nifty-vol').textContent = vol >= 10000000 ? (vol/10000000).toFixed(2) + ' Cr' : (vol/100000).toFixed(2) + ' L';
                    }
                } catch (e) {
                    console.warn("Stats refresh pending...");
                }
            }

            // --- 3. AI Analysis Logic ---
            window.triggerAIAnalysis = async function () {
                const loading = document.getElementById('ai-loading');
                const result = document.getElementById('ai-result-content');
                const waiting = document.getElementById('ai-waiting');
                
                loading.style.display = 'flex';
                result.style.display = 'none';
                waiting.style.display = 'none';

                try {
                    const candles = (window._lastCandles || []).slice(-30);
                    const res = await fetch('/angel/nifty-ai-analyze', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                        },
                        body: JSON.stringify({ interval: window._currentInterval, candles })
                    });

                    const json = await res.json();
                    if (json.success) renderAI(json.data);
                    else waiting.style.display = 'flex';
                } catch (e) {
                    waiting.style.display = 'flex';
                } finally {
                    loading.style.display = 'none';
                }
            };

            function renderAI(d) {
                document.getElementById('ai-result-content').style.display = 'flex';
                document.getElementById('ai-icon').textContent = d.icon;
                document.getElementById('ai-title').textContent = d.title;
                document.getElementById('ai-confidence').textContent = d.confidence;
                document.getElementById('ai-trend').textContent = d.trendAlign;
                document.getElementById('ai-momentum').textContent = d.momentum;
                document.getElementById('ai-vol-sig').textContent = d.volSig;
                document.getElementById('ai-risk').textContent = d.risk;
                document.getElementById('ai-support').textContent = d.keyLevels.support;
                document.getElementById('ai-resist').textContent = d.keyLevels.resistance;
                document.getElementById('ai-updated').textContent = 'Last sync: ' + new Date().toLocaleTimeString();

                const box = document.getElementById('ai-verdict-box');
                const colors = { bullish: ['#f0fdf4', '#86efac'], bearish: ['#fff1f2', '#fecaca'], sideways: ['#fffbeb', '#fde68a'] };
                if (colors[d.verdict]) {
                    box.style.background = colors[d.verdict][0];
                    box.style.borderColor = colors[d.verdict][1];
                }
            }

            // Start Intervals
            setInterval(refreshNiftyLiveStats, 2000);
            document.addEventListener('DOMContentLoaded', refreshNiftyLiveStats);
        })();
</script>

@vite(['resources/js/chart.js', 'resources/js/chatbox.js'])