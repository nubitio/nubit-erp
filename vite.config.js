import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';

import react from '@vitejs/plugin-react';

export default defineConfig({
  resolve: {
    alias: {
      '@app': fileURLToPath(new URL('./assets/app', import.meta.url)),
      '@shared': fileURLToPath(new URL('./assets/shared', import.meta.url)),
      '@platform': fileURLToPath(new URL('./assets/core', import.meta.url)),
      '@features': fileURLToPath(new URL('./assets/features', import.meta.url)),
      '@pages': fileURLToPath(new URL('./assets/pages', import.meta.url)),
      '@widgets': fileURLToPath(new URL('./assets/components', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./assets/layouts', import.meta.url)),
      '@contexts': fileURLToPath(new URL('./assets/contexts', import.meta.url)),
      '@utils': fileURLToPath(new URL('./assets/utils', import.meta.url)),
      '@theme': fileURLToPath(new URL('./assets/theme', import.meta.url)),
      '@dto': fileURLToPath(new URL('./assets/dto', import.meta.url)),
      '@types': fileURLToPath(new URL('./assets/types', import.meta.url)),
      '@assets': fileURLToPath(new URL('./assets', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    allowedHosts: ['.localhost'],
  },
  plugins: [
    react(),
    symfonyPlugin({
      viteDevServerHostname: 'localhost',
    }),
  ],
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.tsx',
      },
    },
  },
});
