import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'resources/css/option-chain.css',   
                'resources/js/option-chain.js', 
                'resources/js/option-chain-chart.js',   
                'resources/js/nifty-option-data-ai-chat.js',
            ],
            refresh: true,
        }),
    ],
     
});
