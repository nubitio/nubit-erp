import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';

import react from '@vitejs/plugin-react';

export default defineConfig({
  server: {
    host: '0.0.0.0',
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
