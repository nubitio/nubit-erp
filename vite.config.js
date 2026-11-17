import { fileURLToPath, URL } from 'node:url';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import symfonyPlugin from '@symfony/reprise/vite';
import { VitePWA } from 'vite-plugin-pwa';
import {
  isTenantShellNavigation,
  shellCacheKeyWillBeUsed,
} from './assets/offline/shellNavigation.js';

import react from '@vitejs/plugin-react';

// Host-visible origin for Symfony script tags. Defaults to 5174 so efact does not
// collide with other local Vite apps that commonly bind localhost:5173.
// Container-based browsers can use VITE_DEV_SERVER_ORIGIN=http://host.docker.internal:5174.
const viteDevServerOrigin = process.env.VITE_DEV_SERVER_ORIGIN ?? 'http://localhost:5174';

const frontendAliases = {
  // Canonical frontend layer aliases (ADR-015). @nubitio/* packages come from npm.
  '@app': fileURLToPath(new URL('./assets/app', import.meta.url)),
  '@shared': fileURLToPath(new URL('./assets/shared', import.meta.url)),
  '@features': fileURLToPath(new URL('./assets/features', import.meta.url)),

  // Supporting aliases outside the canonical layer set
  '@utils': fileURLToPath(new URL('./assets/utils', import.meta.url)),
  '@theme': fileURLToPath(new URL('./assets/theme', import.meta.url)),
  '@assets': fileURLToPath(new URL('./assets', import.meta.url)),
  '@offline': fileURLToPath(new URL('./assets/offline', import.meta.url)),
};

export default defineConfig({
  resolve: {
    alias: frontendAliases,
    dedupe: [
      'react',
      'react-dom',
      'react-router',
      'react-router-dom',
      '@tanstack/react-query',
      '@nubitio/core',
      '@nubitio/crud',
      '@nubitio/ui',
      'i18next',
      'react-i18next',
    ],
  },
  test: {
    environment: 'happy-dom',
    include: ['assets/**/*.test.ts', 'assets/**/*.test.tsx'],
    alias: frontendAliases,
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    allowedHosts: true,
    cors: true,
  },
  plugins: [
    react(),
    symfonyPlugin({
      outputPath: 'public/build',
      publicPath: '/build/',
      devServerOrigin: viteDevServerOrigin,
    }),
    VitePWA({
      outDir: 'public',
      buildBase: '/',
      // The app registers /sw.js manually in assets/app.tsx.
      // Disable vite-plugin-pwa's injected helper so it does not generate and
      // precache build/registerSW.js, which is not served in this Symfony setup.
      injectRegister: false,
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
        // Some vendor chunks can exceed Workbox's default precache limit (2 MiB).
        // Keep them precacheable to avoid failing CI image builds.
        maximumFileSizeToCacheInBytes: 10 * 1024 * 1024,
        // Keep the existing asset graph until the cashier accepts the update.
        // appUpdate.ts sends SKIP_WAITING after checkout/sync are idle, then
        // reloads on controllerchange to avoid mixing old and new chunks.
        skipWaiting: false,
        clientsClaim: true,
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
            //
            // ADR-020 D4: the shell is ONE document for every client route, cached under
            // the key `/`. Whatever URL was asked for, an offline reload is answered
            // with that copy, so /billing/sales works offline without having been
            // visited online. Server-owned and other-SPA namespaces (/admin, /account,
            // /receipt…) are excluded so they can never overwrite the shell.
            urlPattern: isTenantShellNavigation,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'app-shell',
              plugins: [{ cacheKeyWillBeUsed: shellCacheKeyWillBeUsed }],
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
    // vite-plugin-pwa injects some helper/root-level entries into Workbox's
    // additionalManifestEntries (which runs after manifestTransforms, so they
    // cannot be filtered there).
    // In this Symfony-served app those URLs are not served at
    // the paths Workbox precaches, so SW install fails with
    // bad-precaching-response. Strip them from the generated sw.js after build.
    {
      name: 'pwa-strip-manifest-from-precache',
      apply: 'build',
      enforce: 'post',
      closeBundle() {
        const swPath = new URL('./public/sw.js', import.meta.url).pathname;
        if (!existsSync(swPath)) return;
        const before = readFileSync(swPath, 'utf8');
        const after = before
          .replace(/,\s*\{url:"manifest\.webmanifest",revision:"[^"]*"\}/g, '')
          .replace(/,\s*\{url:"build\/registerSW\.js",revision:"[^"]*"\}/g, '');
        if (before !== after) {
          writeFileSync(swPath, after, 'utf8');
          // eslint-disable-next-line no-console -- build plugin status message is intentional CLI output.
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
        // Legacy SCSS (Bootstrap-derived styles, @import, color functions) triggers
        // Sass 1.80+ deprecation warnings. Silence them to keep build output clean.
        silenceDeprecations: ['global-builtin', 'color-functions', 'import'],
        loadPaths: [fileURLToPath(new URL('./assets', import.meta.url))],
      },
    },
  },
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.tsx',
        'control-plane': './assets/control-plane.tsx',
        'account-portal': './assets/account-portal.tsx',
      },
      output: {
        manualChunks(id) {
          // Keep the Nubit packages in one chunk to avoid circular
          // execution-order warnings between facade re-exports.
          if (id.includes('node_modules/@nubitio/')) {
            return 'nubit';
          }
        },
      },
    },
  },
});
