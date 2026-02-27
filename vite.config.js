import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import { VitePWA } from 'vite-plugin-pwa';

import react from '@vitejs/plugin-react';

export default defineConfig({
  resolve: {
    alias: {
      '@app': fileURLToPath(new URL('./assets/app', import.meta.url)),
      '@shared': fileURLToPath(new URL('./assets/shared', import.meta.url)),
      '@platform': fileURLToPath(new URL('./assets/core', import.meta.url)),
      '@features': fileURLToPath(new URL('./assets/features', import.meta.url)),
      '@widgets': fileURLToPath(new URL('./assets/components', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./assets/layouts', import.meta.url)),
      '@contexts': fileURLToPath(new URL('./assets/contexts', import.meta.url)),
      '@utils': fileURLToPath(new URL('./assets/utils', import.meta.url)),
      '@theme': fileURLToPath(new URL('./assets/theme', import.meta.url)),
      '@dto': fileURLToPath(new URL('./assets/dto', import.meta.url)),
      '@types': fileURLToPath(new URL('./assets/types', import.meta.url)),
      '@assets': fileURLToPath(new URL('./assets', import.meta.url)),
      '@offline': fileURLToPath(new URL('./assets/offline', import.meta.url)),
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
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'robots.txt'],
      manifest: {
        name: 'efact POS',
        short_name: 'efact',
        theme_color: '#1463ff',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/#/pos',
      },
      workbox: {
        // DevExtreme/vendor chunks can exceed Workbox default precache limit (2 MiB).
        // Keep them precacheable to avoid failing CI image builds.
        maximumFileSizeToCacheInBytes: 10 * 1024 * 1024,
        navigateFallback: '/index.html',
        runtimeCaching: [
          {
            urlPattern: ({ request }) => request.mode === 'navigate',
            handler: 'CacheFirst',
            options: {
              cacheName: 'app-shell',
              expiration: {
                maxEntries: 20,
                maxAgeSeconds: 7 * 24 * 60 * 60,
              },
            },
          },
          {
            urlPattern: ({ request }) =>
              request.destination === 'script' ||
              request.destination === 'style' ||
              request.destination === 'font' ||
              request.destination === 'image',
            handler: 'CacheFirst',
            options: {
              cacheName: 'static-assets',
              expiration: {
                maxEntries: 200,
                maxAgeSeconds: 30 * 24 * 60 * 60,
              },
            },
          },
          {
            urlPattern: ({ url, request }) =>
              request.method === 'GET' && url.pathname.includes('/api/products'),
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'api-products',
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
          {
            urlPattern: ({ url, request }) =>
              request.method === 'GET' && url.pathname.includes('/api/customers'),
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'api-customers',
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
        ],
      },
      devOptions: {
        enabled: true,
      },
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
