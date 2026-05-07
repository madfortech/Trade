import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
plugins: [
laravel({
input: [
 
            // Main App
            'resources/js/app.js',

            // Existing Files
            'resources/js/chart.js',
            'resources/js/chatbox.js',

            // Option Chain
            'resources/js/option-chain.js',
            'resources/js/option-chain-chart.js',
            'resources/js/nifty-option-data-ai-chat.js',
        ],

        refresh: true,
    }),
],

server: {
    host: 'trade.test',

    hmr: {
        host: 'trade.test',
    },
},
 

});
