'use strict';

import { _csrf, _el, _tnow } from '../utils';
import { userMsg, typingMsg } from './chat-ui';

export async function sendChat() {

    const inp = _el('chatInput');

    if (!inp) return;

    const text = inp.value.trim();

    if (!text) return;

    userMsg(text);

    inp.value = '';

    const typing = typingMsg();

    try {

        const res = await fetch('/angel/chart-chat', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': _csrf(),
            },

            body: JSON.stringify({
                message: text,
                strike: window._modalStrike,
                side: window._modalSide,
            }),
        });

        const j = await res.json();

        if (typing) {

            typing.innerHTML = `
                <div class="chat-bubble">
                    ${j.reply || 'No reply'}
                </div>

                <span class="chat-time">
                    ${_tnow()}
                </span>
            `;
        }

    } catch (e) {

        if (typing) {
            typing.innerHTML = `
                <div class="chat-bubble">
                    ❌ Network error
                </div>
            `;
        }
    }
}