import { defineConfig } from 'tsdown';

const NUBIT_PACKAGES = ['@nubit/core', '@nubit/ui', '@nubit/admin', '@nubit/api-platform'];

// IDs that must never be bundled regardless of tsconfig path aliases.
const ALWAYS_EXTERNAL = (id: string) =>
  /\.s?css$/.test(id) ||
  NUBIT_PACKAGES.some((pkg) => id === pkg || id.startsWith(pkg + '/'));

const sharedConfig = {
  format: ['esm', 'cjs'],
  dts: true,
  clean: true,
  sourcemap: false,
  // Rolldown receives the original specifier before tsconfig alias resolution,
  // so we can externalize '@nubit/core' and CSS files here.
  inputOptions: {
    external: ALWAYS_EXTERNAL,
  },
} satisfies Parameters<typeof defineConfig>[0];

export default [
  // ── @nubit/core ──────────────────────────────────────────────────────────────
  // Runtime/CRUD facade. Cross-package @nubit/* imports stay external.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/core/public.ts' },
    outDir: 'assets/core/dist',
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

  // ── @nubit/api-platform ───────────────────────────────────────────────────────
  // Bundles Hydra/OpenAPI parser code. All @nubit/core references are external.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/api-platform/public.ts' },
    outDir: 'assets/api-platform/dist',
  }),

  // ── @nubit/react-admin ────────────────────────────────────────────────────────
  // Umbrella re-export: all 4 packages are external, this bundle is near-zero bytes.
  defineConfig({
    ...sharedConfig,
    entry: { index: 'assets/react-admin/public.ts' },
    outDir: 'assets/react-admin/dist',
  }),
];
