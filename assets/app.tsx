import { Settings } from 'luxon';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { EfactApp } from '@app/shell/EfactApp';
import { ErrorBoundary } from '@shared/components';
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

window.addEventListener('load', () => {
  if ('serviceWorker' in navigator) {
    void navigator.serviceWorker.register('/sw.js');
  }

  const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
  root.render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <ErrorBoundary>
          <EfactApp />
        </ErrorBoundary>
        {import.meta.env.DEV && (
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
