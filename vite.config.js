import { fileURLToPath, URL } from 'node:url';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import { VitePWA } from 'vite-plugin-pwa';

import react from '@vitejs/plugin-react';

export default defineConfig({
  resolve: {
    alias: {
      '@app': fileURLToPath(new URL('./assets/app', import.meta.url)),
      '@shared': fileURLToPath(new URL('./assets/shared', import.meta.url)),
      '@core': fileURLToPath(new URL('./assets/core', import.meta.url)),
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
    allowedHosts: 'all',
    cors: true,
  },
  plugins: [
    react(),
    symfonyPlugin({
      viteDevServerHostname: 'localhost',
    }),
    VitePWA({
      outDir: 'public',
      buildBase: '/',
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
        globPatterns: ['build/**/*.{js,css,woff,woff2,png,ico,svg}'],
        // DevExtreme/vendor chunks can exceed Workbox default precache limit (2 MiB).
        // Keep them precacheable to avoid failing CI image builds.
        maximumFileSizeToCacheInBytes: 10 * 1024 * 1024,
        // Explicitly disable navigateFallback: the app shell is served by
        // Symfony/PHP (index.php → Twig) — there is no static /index.html.
        // Without this, Workbox auto-adds a NavigationRoute for 'index.html'
        // which throws at runtime because it's never precached.
        navigateFallback: null,
        runtimeCaching: [
          {
            // Cache the Symfony-rendered HTML shell. NetworkFirst ensures the SW
            // tries the network first (5 s timeout) and falls back to the cached
            // response when offline, enabling offline reloads after the first visit.
            urlPattern: ({ request, url }) =>
              request.mode === 'navigate' && !url.pathname.startsWith('/api/'),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'app-shell',
              networkTimeoutSeconds: 5,
              expiration: {
                maxEntries: 10,
                maxAgeSeconds: 7 * 24 * 60 * 60,
              },
              cacheableResponse: {
                statuses: [200],
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
        enabled: false,
      },
    }),
    // vite-plugin-pwa injects manifest.webmanifest into Workbox's additionalManifestEntries
    // (which runs after manifestTransforms, so it can't be filtered there).
    // vite-plugin-symfony routes all Vite assets to public/build/, so the manifest is
    // served at /build/manifest.webmanifest — but the precache entry targets the root-level
    // /manifest.webmanifest, causing a bad-precaching-response 404 at SW install time.
    // This plugin strips that entry from the generated sw.js after the fact.
    {
      name: 'pwa-strip-manifest-from-precache',
      apply: 'build',
      enforce: 'post',
      closeBundle() {
        const swPath = new URL('./public/sw.js', import.meta.url).pathname;
        if (!existsSync(swPath)) return;
        const before = readFileSync(swPath, 'utf8');
        // Entry is always appended last: ,{url:"manifest.webmanifest",revision:"<hash>"}
        const after = before.replace(/,\s*\{url:"manifest\.webmanifest",revision:"[^"]*"\}/g, '');
        if (before !== after) {
          writeFileSync(swPath, after, 'utf8');
          console.log(
            '✓ pwa-strip-manifest-from-precache: removed manifest.webmanifest from sw.js precache list',
          );
        }
      },
    },
  ],
  css: {
    preprocessorOptions: {
      scss: {
        // DevExtreme SCSS still uses legacy color functions (lighten, desaturate, etc.)
        // that Sass 1.80+ deprecated. Silence until DevExtreme updates their SCSS.
        silenceDeprecations: ['global-builtin', 'color-functions', 'import'],
      },
    },
  },
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.tsx',
      },
    },
  },
  test: {
    environment: 'node',
    globals: true,
    include: ['assets/**/*.test.ts', 'assets/**/*.test.tsx'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: './var/coverage/frontend',
      include: [
        'assets/features/pos/pages/posHelpers.ts',
        'assets/offline/api.ts',
        'assets/core/form/useFormSubmit.ts',
        'assets/core/form/FormUtils.tsx',
        'assets/core/datagrid/DxDataGridUtils.tsx',
        'assets/features/sales/pages/SaleFields.tsx',
      ],
      thresholds: {
        // Per-file thresholds — tightened individually as coverage improves
        'assets/features/pos/pages/posHelpers.ts': {
          lines: 85,
          functions: 85,
          statements: 85,
          branches: 75,
        },
        'assets/offline/api.ts': { lines: 85, functions: 85, statements: 85, branches: 85 },
        // Partial coverage — core serialization paths tested, HTTP and render paths excluded
        'assets/core/form/useFormSubmit.ts': {
          lines: 30,
          functions: 10,
          statements: 30,
          branches: 30,
        },
        'assets/core/form/FormUtils.tsx': {
          lines: 25,
          functions: 20,
          statements: 25,
          branches: 10,
        },
        'assets/core/datagrid/DxDataGridUtils.tsx': {
          lines: 60,
          functions: 75,
          statements: 60,
          branches: 45,
        },
        'assets/features/sales/pages/SaleFields.tsx': {
          lines: 35,
          functions: 15,
          statements: 35,
          branches: 10,
        },
      },
    },
  },
});
