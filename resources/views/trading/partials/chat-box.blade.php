<div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.04); display:flex; flex-direction:column; height:580px;">
    {{-- Header --}}
    <div style="display:flex; align-items:center; gap:8px; padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
        <span style="font-size:14px;">💬</span>
        <span style="font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#475569; font-family:'JetBrains Mono',monospace;">AI Chat</span>
    </div>

    {{-- Message Area --}}
    <div id="chat-messages" style="flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px;">
        <div class="nt-chat-welcome" style="display:flex; flex-direction:column; align-items:center; text-align:center; padding:20px 10px; gap:10px; color:#94a3b8; font-size:11px;">
            <div style="font-size:28px;">🤖</div>
            <p>
                AI-generated analysis for educational purposes only. Market investments carry risk. Verify with a financial advisor before trading.
            </p>
        </div>
    </div>

    {{-- Input Area --}}
    <div class="p-2">
        
        <div class="mb-2">
            <input id="chat-input" type="text" class="w-full px-4 py-2" placeholder="Ask me anything about NIFTY..."/>
        </div>

        <div class="mb-2">
            <button id="chat-send" class="bg-blue-500 w-full text-white border-none rounded-lg px-3 py-2     cursor-pointer">
                 
                Search
            </button>
        </div>
    </div>

</div>