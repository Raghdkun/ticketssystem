import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                // Latin: Bricolage Grotesque displays, Public Sans reads.
                // Public Sans has a taller x-height than Instrument Sans,
                // which is what keeps 15px legible on a phone at arm's length.
                bunny('Bricolage Grotesque', {
                    weights: [500, 600, 700],
                }),
                bunny('Public Sans', {
                    weights: [400, 500, 600, 700],
                }),
                // Arabic display. IBM Plex Sans Arabic still carries the text.
                bunny('Rubik', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('IBM Plex Mono', {
                    weights: [400, 500],
                }),
                // Instrument Sans has no Arabic coverage, so Arabic would fall
                // back to whatever the OS provides. This ships a real Arabic face.
                bunny('IBM Plex Sans Arabic', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
