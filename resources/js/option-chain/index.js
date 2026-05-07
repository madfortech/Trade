'use strict';

import { installChartHooks } from './chart/chart-hooks';
import { runAIAnalysis } from './ai/ai-analysis';
import { sendChat } from './chat/chat-api';

window.runAIAnalysis = runAIAnalysis;

window.sendChat = sendChat;

document.addEventListener('DOMContentLoaded', () => {

    installChartHooks();
});