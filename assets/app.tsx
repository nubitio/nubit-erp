import { Settings } from 'luxon';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { UiStringsProvider, ES_UI_STRINGS } from '@nubitio/ui';
// Vendor styles first so app-owned SCSS (styles.scss, custom.scss) reliably
// cascades after them — lets eFact override design tokens and shell chrome.
import '@nubitio/react-admin/style.css';
import '@nubitio/ui/style.css';
import { EfactApp } from '@app/shell/EfactApp';
import { ErrorBoundary } from '@shared/components';
import { installPosBootstrapCache } from '@offline/posBootstrap';
import { startAppUpdates } from '@offline/appUpdate';
import { AppUpdateNotice } from '@app/shell/components/AppUpdateNotice';
import './i18n';
import './app.scss';

// Ensure all luxon DateTime operations default to Peru timezone (UTC-5, no DST)
Settings.defaultZone = 'America/Lima';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
    },
  },
});

installPosBootstrapCache(queryClient);

window.addEventListener('load', () => {
  startAppUpdates();

  const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
  root.render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <UiStringsProvider strings={ES_UI_STRINGS}>
          <AppUpdateNotice />
          <ErrorBoundary>
            <EfactApp />
          </ErrorBoundary>
        </UiStringsProvider>
        {import.meta.env.DEV && import.meta.env.VITE_SHOW_DEVTOOLS === 'true' && (
          <React.Suspense fallback={null}>
            <DevtoolsLoader />
          </React.Suspense>
        )}
      </QueryClientProvider>
    </React.StrictMode>,
  );
});

// Lazy-load devtools only in dev to keep production bundle clean
const ReactQueryDevtools = import.meta.env.DEV
  ? React.lazy(async () => {
      const mod = await import('@tanstack/react-query-devtools');
      return { default: mod.ReactQueryDevtools };
    })
  : null;

function DevtoolsLoader() {
  if (!ReactQueryDevtools) return null;
  return <ReactQueryDevtools initialIsOpen={false} />;
}
