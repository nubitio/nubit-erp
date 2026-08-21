import { Settings } from 'luxon';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { UiStringsProvider, ES_UI_STRINGS } from '@nubitio/ui';
import { EfactApp } from '@app/shell/EfactApp';
import { ErrorBoundary } from '@shared/components';
import './i18n';
import '@nubitio/react-admin/style.css';
import '@nubitio/ui/style.css';
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

window.addEventListener('load', () => {
  if ('serviceWorker' in navigator) {
    void navigator.serviceWorker.register('/sw.js').then((registration) => {
      // skipWaiting (vite.config.js) activates a new SW immediately instead of
      // waiting for every tab to close. Force a full reload on takeover so the
      // swap always lands as one clean version change, never a mid-session mix
      // of old cached chunks and a new SW (that mix is what caused the
      // "Invalid hook call" / infinite session-expired loop after a deploy).
      let reloading = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloading) return;
        reloading = true;
        window.location.reload();
      });

      // The SPA uses HashRouter, so route changes never trigger a real
      // navigation — the browser's built-in "check for SW update on
      // navigate" never fires during a long-lived POS session. Poll
      // explicitly instead.
      const checkForUpdate = () => void registration.update();
      window.setInterval(checkForUpdate, 30 * 60 * 1000);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkForUpdate();
      });
    });
  }

  const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
  root.render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <UiStringsProvider strings={ES_UI_STRINGS}>
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
