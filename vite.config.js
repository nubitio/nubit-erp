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
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return undefined;
          }

          if (
            id.includes('/react/') ||
            id.includes('/react-dom/') ||
            id.includes('/react-router-dom/')
          ) {
            return 'vendor-react';
          }

          if (id.includes('/html2canvas')) {
            return 'vendor-html2canvas';
          }

          if (id.includes('/exceljs')) {
            return 'vendor-exceljs';
          }

          if (id.includes('/jspdf') || id.includes('/file-saver')) {
            return 'vendor-pdf-export';
          }

          if (id.includes('/devextreme/pdf_exporter') || id.includes('/devextreme/excel_exporter')) {
            return 'vendor-dx-exporters';
          }

          if (id.includes('/devextreme-react/data-grid') || id.includes('/devextreme-react/tree-list')) {
            return 'vendor-dx-react-grid';
          }

          if (id.includes('/devextreme-react')) {
            return 'vendor-devextreme-react';
          }

          if (
            id.includes('/devextreme/ui/data_grid') ||
            id.includes('/devextreme/ui/tree_list') ||
            id.includes('/devextreme/ui/scheduler') ||
            id.includes('/devextreme/viz/')
          ) {
            return 'vendor-devextreme-heavy';
          }

          if (id.includes('/devextreme')) {
            return 'vendor-devextreme';
          }

          if (
            id.includes('/axios') ||
            id.includes('/rxjs') ||
            id.includes('/dayjs') ||
            id.includes('/luxon')
          ) {
            return 'vendor-data';
          }

          if (
            id.includes('/marked') ||
            id.includes('/unified') ||
            id.includes('/remark-') ||
            id.includes('/rehype-') ||
            id.includes('/openai')
          ) {
            return 'vendor-ai';
          }

          if (id.includes('/lucide-react') || id.includes('/@phosphor-icons/')) {
            return 'vendor-icons';
          }

          if (id.includes('/qz-tray')) {
            return 'vendor-printing';
          }

          return 'vendor';
        },
      },
    },
  },
});
