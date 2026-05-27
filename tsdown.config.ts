import { defineConfig } from 'tsdown';

const NUBIT_PACKAGES = ['@nubit/core', '@nubit/crud', '@nubit/ui', '@nubit/admin', '@nubit/hydra'];

// External: only cross-package @nubit/* imports. CSS/SCSS is processed by
// @tsdown/css and extracted into dist/index.css alongside the JS bundles.
const ALWAYS_EXTERNAL = (id: string) =>
  NUBIT_PACKAGES.some((pkg) => id === pkg || id.startsWith(pkg + '/'));

const sharedConfig = {
  format: ['esm', 'cjs'],
  dts: true,
  clean: true,
  sourcemap: false,
  // @tsdown/css compiles SCSS and extracts all component styles to dist/index.css.
  // `loadPaths` lets every SCSS file resolve `@use 'variables'` from assets/.
  css: {
    preprocessorOptions: {
      scss: {
        loadPaths: ['assets'],
      },
    },
  },
  inputOptions: {
    external: ALWAYS_EXTERNAL,
  },
} satisfies Parameters<typeof defineConfig>[0];

export default [
  // ── @nubit/core ──────────────────────────────────────────────────────────────
  // Runtime primitives only: HTTP, Mercure, config, events, base data types.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/core/public.ts' },
    outDir: 'assets/core/dist',
  }),

  // ── @nubit/crud ──────────────────────────────────────────────────────────────
  // CRUD engine, field DSL, form/grid views. Depends on @nubit/core + @nubit/ui.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/crud/public.ts' },
    outDir: 'assets/crud/dist',
  }),

  // ── @nubit/admin ─────────────────────────────────────────────────────────────
  // Self-contained: AdminShell, AdminHeader, AdminSidebarMenu, useScreenSize.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/admin/public.ts' },
    outDir: 'assets/admin/dist',
  }),

  // ── @nubit/ui ─────────────────────────────────────────────────────────────────
  // Visual primitives: AppDialog, ConfirmDialog, and related styles.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/ui/public.ts' },
    outDir: 'assets/ui/dist',
  }),

  // ── @nubit/hydra ─────────────────────────────────────────────────────────────
  // Bundles Hydra/OpenAPI parser code. All @nubit/core references are external.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/hydra/public.ts' },
    outDir: 'assets/hydra/dist',
  }),

  // ── @nubit/react-admin ────────────────────────────────────────────────────────
  // Umbrella re-export: all 4 packages are external, this bundle is near-zero bytes.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/react-admin/public.ts' },
    outDir: 'assets/react-admin/dist',
  }),
];
