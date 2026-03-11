import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: true, // listen on 0.0.0.0 so dev server is reachable on local network (e.g. 192.168.x.x:5173)
    },
});
