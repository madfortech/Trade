import './bootstrap';

import Alpine from 'alpinejs';

// KEEP THESE
import './chart.js';

// NEW MODULAR OPTION CHAIN
import './option-chain/index';

// Prevent multiple Alpine instances
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}