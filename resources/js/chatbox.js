/**
 * chatbox.js
 * Nifty Live Terminal — Chat Box with AI backend
 * Place in: resources/js/chatbox.js
 */

document.addEventListener('DOMContentLoaded', function () {

    const inputEl    = document.getElementById('chat-input');
    const sendBtn    = document.getElementById('chat-send');
    const messagesEl = document.getElementById('chat-messages');

    if (!inputEl || !sendBtn || !messagesEl) return;

    const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Send message ──────────────────────────────────────────────────────────
    function sendMessage() {
        const text = inputEl.value.trim();
        if (!text) return;

        appendUserMsg(text);
        inputEl.value = '';
        inputEl.focus();

        callAI(text);
    }

    // ── Call AI backend ───────────────────────────────────────────────────────
    async function callAI(message) {
        // Typing indicator
        const typingId = showTyping();

        try {
            const interval = window._currentInterval || '5m';
            const candles  = (window._lastCandles || []).slice(-20);

            const res = await fetch('/angel/nifty-chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': _csrf,
                },
                body: JSON.stringify({
                    message,
                    interval,
                    candles,
                    context: {
                        trend:    window._seData?.trend    || '—',
                        strength: window._seData?.strength || '—',
                        vwap:     window._seData?.vwap     || '—',
                        macd:     window._seData?.macd     || '—',
                        stoch:    window._seData?.stoch    || '—',
                        signal:   window._seData?.signal   || '—',
                    },
                }),
            });

            removeTyping(typingId);

            const json = await res.json();

            if (json.success && json.reply) {
                appendAIMsg(json.reply);
            } else {
                appendAIMsg('❌ Response nahi mila. Dobara try karo.');
            }

        } catch (err) {
            removeTyping(typingId);
            appendAIMsg('❌ Network error: ' + err.message);
            console.error('Chat AI error:', err);
        }
    }

    // ── Append user message ───────────────────────────────────────────────────
    function appendUserMsg(text) {
        removePlaceholder();
        const div = document.createElement('div');
        div.className = 'nt-msg-user';
        div.textContent = text;
        messagesEl.appendChild(div);
        scrollBottom();
    }

    // ── Append AI message (supports HTML from backend) ────────────────────────
    function appendAIMsg(html) {
        removePlaceholder();
        const div = document.createElement('div');
        div.className = 'nt-msg-ai';
        div.innerHTML = html; // AI returns Hinglish with <b>, <br> tags
        messagesEl.appendChild(div);
        scrollBottom();
    }

    // ── Typing indicator ──────────────────────────────────────────────────────
    function showTyping() {
        removePlaceholder();
        const id  = 'typing-' + Date.now();
        const div = document.createElement('div');
        div.className = 'nt-msg-ai';
        div.id        = id;
        div.innerHTML = '<span style="letter-spacing:3px;color:#94a3b8;">•••</span>';
        messagesEl.appendChild(div);
        scrollBottom();
        return id;
    }

    function removeTyping(id) {
        document.getElementById(id)?.remove();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function removePlaceholder() {
        messagesEl.querySelector('.nt-chat-welcome')?.remove();
        messagesEl.querySelector('.chat-placeholder')?.remove();
    }

    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // ── Event listeners ───────────────────────────────────────────────────────
    sendBtn.addEventListener('click', sendMessage);

    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ── Public API ────────────────────────────────────────────────────────────
    window.appendChatMessage = function (role, html) {
        if (role === 'user') appendUserMsg(html);
        else appendAIMsg(html);
    };

    window.scrollChatToBottom = scrollBottom;

});
