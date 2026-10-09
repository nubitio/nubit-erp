import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, UiStringsProvider, ES_UI_STRINGS } from '@nubitio/ui';
import { ControlPlaneApp } from '@features/control-plane/ControlPlaneApp';
import { CpToastProvider } from '@features/control-plane/providers/CpToastProvider';
import '@nubitio/admin/style.css';
import '@nubitio/ui/style.css';
import '@phosphor-icons/web/regular';
import '@features/control-plane/styles/control-plane.scss';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 15_000,
    },
  },
});

const root = document.getElementById('control-plane-root');
if (root) {
  ReactDOM.createRoot(root).render(
    <React.StrictMode>
      <ThemeProvider storageKey="cp-theme" themePrefix="app-theme-" basePath="/themes/">
        <QueryClientProvider client={queryClient}>
          <UiStringsProvider strings={ES_UI_STRINGS}>
            <CpToastProvider>
              <ControlPlaneApp />
            </CpToastProvider>
          </UiStringsProvider>
        </QueryClientProvider>
      </ThemeProvider>
    </React.StrictMode>,
  );
}
