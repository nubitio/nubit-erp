import { fileURLToPath, URL } from 'node:url';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import { VitePWA } from 'vite-plugin-pwa';

import react from '@vitejs/plugin-react';

// VITE_DEV_SERVER_HOSTNAME allows overriding the hostname that Symfony uses
// when generating <script src="http://<hostname>:5173/..."> tags.
//
// - Default: 'localhost'  → works for the developer's browser (host machine)
// - Set to 'host.docker.internal' when running Playwright from inside Docker,
//   because inside a container 'localhost' resolves to the container itself,
//   not the host. The port 5173 must be mapped in docker-compose (5173:5173).
//
// Usage: VITE_DEV_SERVER_HOSTNAME=host.docker.internal npm run dev
const viteDevServerHostname = process.env.VITE_DEV_SERVER_HOSTNAME ?? 'localhost';

const frontendAliases = {
  // Nubit package facades — prefer these over direct @core/* / @app/shell imports.
  '@nubit/react-admin': fileURLToPath(new URL('./assets/react-admin/public.ts', import.meta.url)),
  '@nubit/core': fileURLToPath(new URL('./assets/core/public.ts', import.meta.url)),
  '@nubit/ui': fileURLToPath(new URL('./assets/ui/public.ts', import.meta.url)),
  '@nubit/admin': fileURLToPath(new URL('./assets/admin/public.ts', import.meta.url)),
  '@nubit/api-platform': fileURLToPath(new URL('./assets/api-platform/public.ts', import.meta.url)),

  // Canonical frontend layer aliases (ADR-015)
  '@app': fileURLToPath(new URL('./assets/app', import.meta.url)),
  '@core': fileURLToPath(new URL('./assets/core', import.meta.url)),
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
  },
  test: {
    environment: 'happy-dom',
    include: ['assets/**/*.test.ts', 'assets/**/*.test.tsx'],
    alias: frontendAliases,
  },
  server: {
    host: '0.0.0.0',
    allowedHosts: true,
    cors: true,
  },
  plugins: [
    react(),
    symfonyPlugin({
      // 'localhost' → Symfony genera URLs con localhost:5173, accesible tanto
      // desde el navegador del host como desde contenedores Docker (vía el
      // puerto mapeado 5173:5173). Ver: symfony-vite.pentatrion.com/guide/tips#docker
      viteDevServerHostname,
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
    // vite-plugin-pwa injects some helper/root-level entries into Workbox's
    // additionalManifestEntries (which runs after manifestTransforms, so they
    // cannot be filtered there).
    // In this Symfony + vite-plugin-symfony setup those URLs are not served at
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
        loadPaths: [fileURLToPath(new URL('./assets', import.meta.url))],
      },
    },
  },
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.tsx',
      },
      output: {
        manualChunks(id) {
          // Keep the in-repo Nubit facades together while CRUD/API Platform are
          // still being split. This avoids circular execution-order warnings
          // between facade re-exports and implementation modules.
          if (
            id.includes('/assets/core/') ||
            id.includes('/assets/ui/') ||
            id.includes('/assets/admin/') ||
            id.includes('/assets/api-platform/') ||
            id.includes('/assets/react-admin/')
          ) {
            return 'nubit';
          }
        },
      },
    },
  },
});
