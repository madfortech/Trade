import './bootstrap';

import Alpine from 'alpinejs';

import './option-chain.js';
import './option-chain-chart.js';

import './indicators/scalp-mode.js';
import './indicators/indicator-math.js';
import './indicators/short-cover.js';
import './indicators/trend-dashboard.js';

import './chart.js';
import './chatbox.js';

// Prevent multiple Alpine instances
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}


