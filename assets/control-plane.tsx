import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, UiStringsProvider, ES_UI_STRINGS } from '@nubitio/ui';
import { ControlPlaneApp } from '@features/control-plane/ControlPlaneApp';
import '@nubitio/ui/style.css';
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
            <ControlPlaneApp />
          </UiStringsProvider>
        </QueryClientProvider>
      </ThemeProvider>
    </React.StrictMode>,
  );
}