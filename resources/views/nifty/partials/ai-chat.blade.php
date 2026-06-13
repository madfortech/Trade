                    <!-- <div class="flex-1 flex flex-col">

                        <div id="chatMessages"
                            class="flex-1 overflow-y-auto p-3">
                        </div>

                        <div class="border-t p-3 bg-white">

                            <div class="flex items-center gap-2">

                                <input type="text"
                                    id="chatInput"
                                    placeholder="Ask AI..."
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">

                                <button onclick="sendChat()"
                                        class="w-11 h-11 rounded-lg bg-indigo-600 text-white">

                                    ➤
                                </button>
                            </div>
                        </div>
                    </div> -->

                    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">

    <!-- CHAT AREA -->
    <div id="chatMessages"
        class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-3 min-h-0">

    </div>

    <!-- INPUT -->
    <div class="border-t p-3 bg-white flex-shrink-0">

        <div class="flex items-center gap-2">

            <input type="text"
                id="chatInput"
                placeholder="Ask AI..."
                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none">

            <button onclick="sendChat()"
                    class="w-11 h-11 rounded-lg bg-indigo-600 text-white flex items-center justify-center flex-shrink-0">

                ➤
            </button>

        </div>
    </div>
</div>
<style>

.chat-msg{
    display:flex;
    flex-direction:column;
    margin-bottom:12px;
}

.chat-msg.user{
    align-items:flex-end;
}

.chat-msg.bot{
    align-items:flex-start;
}

.chat-bubble{
    max-width:85%;
    padding:10px 14px;
    border-radius:16px;
    font-size:12px;
    line-height:1.5;
    word-break:break-word;
}

.chat-msg.user .chat-bubble{
    background:#4f46e5;
    color:#fff;
    border-bottom-right-radius:4px;
}

.chat-msg.bot .chat-bubble{
    background:#f1f5f9;
    color:#0f172a;
    border-bottom-left-radius:4px;
}

.chat-time{
    margin-top:4px;
    font-size:10px;
    color:#94a3b8;
}

</style>