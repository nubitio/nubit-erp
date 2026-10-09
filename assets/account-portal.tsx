import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, UiStringsProvider, ES_UI_STRINGS } from '@nubitio/ui';
import { AccountPortalApp } from '@features/account-portal/AccountPortalApp';
import { ApToastProvider } from '@features/account-portal/providers/ApToastProvider';
import '@nubitio/admin/style.css';
import '@nubitio/ui/style.css';
import '@phosphor-icons/web/regular';
import '@features/account-portal/styles/account-portal.scss';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 10_000,
    },
  },
});

const root = document.getElementById('account-portal-root');
if (root) {
  ReactDOM.createRoot(root).render(
    <React.StrictMode>
      <ThemeProvider storageKey="account-portal-theme" themePrefix="app-theme-" basePath="/themes/">
        <QueryClientProvider client={queryClient}>
          <UiStringsProvider strings={ES_UI_STRINGS}>
            <ApToastProvider>
              <AccountPortalApp />
            </ApToastProvider>
          </UiStringsProvider>
        </QueryClientProvider>
      </ThemeProvider>
    </React.StrictMode>,
  );
}
