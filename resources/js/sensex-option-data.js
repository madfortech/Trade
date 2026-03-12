const SENSEX_BASE='/angel/sensex-option-chain',CANDLE_URL='/angel/sensex-candle-data',REFRESH_URL='/angel/sensex-chain-refresh',LIVE_TICK_URL='/angel/sensex-live-tick',SENSEX_AI_ANALYZE='/angel/sensex-ai-analyze',SENSEX_CHAT_URL='/angel/sensex-chat',SENSEX_CHART_CHAT='/angel/sensex-chart-chat';
const CSRF=document.querySelector('meta[name="csrf-token"]')?.content||'';

let G={chart:null,series:null,rawCandles:[],interval:'FIVE_MINUTE',token:null,exchange:'BFO',chartType:'candlestick',currentCandle:null,lastLtp:null,tickTimer:null,tickMs:2000,autoTimer:null,autoOn:false,expiry:window.SENSEX_SELECTED_EXPIRY || '',spot:Number(window.SENSEX_SPOT || 0),label:'',strike:0,side:'',chatHistory:[],chatOpen:false,modalChatHistory:[],modalChatTyping:false,sideChatTyping:false,aiAnalyzing:false};

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
