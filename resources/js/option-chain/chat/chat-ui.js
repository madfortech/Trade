'use strict';

import { _el, _esc, _tnow } from '../utils';

export function userMsg(text) {

    const m = _el('chatMessages');

    if (!m) return;

    const el = document.createElement('div');

    el.className = 'chat-msg user';

    el.innerHTML = `
        <div class="chat-bubble">${_esc(text)}</div>
        <span class="chat-time">${_tnow()}</span>
    `;

    m.appendChild(el);

    m.scrollTop = m.scrollHeight;
}

export function botMsg(html) {

    const m = _el('chatMessages');

    if (!m) return;

    const el = document.createElement('div');

    el.className = 'chat-msg bot';

    el.innerHTML = `
        <div class="chat-bubble">${html}</div>
        <span class="chat-time">${_tnow()}</span>
    `;

    m.appendChild(el);

    m.scrollTop = m.scrollHeight;

    return el;
}

export function typingMsg() {

    const m = _el('chatMessages');

    if (!m) return null;

    const el = document.createElement('div');

    el.id = 'typing-indicator';

    el.innerHTML = `
        <div class="chat-bubble">•••</div>
    `;

    m.appendChild(el);

    return el;
}