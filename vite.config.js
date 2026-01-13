import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                'cad-render': 'js/cad-render.module.js',
            },
            output: {
                format: 'es',
                entryFileNames: '[name].js',
                manualChunks: {
                    'threejs-vendor': ['three'],
                },
            },
        },
    }, 
});
