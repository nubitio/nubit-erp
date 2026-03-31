import React, { ReactNode } from 'react';
import { useLocation } from 'react-router-dom';
import { ErrorBoundary } from './ErrorBoundary';
import { PageErrorFallback } from './PageErrorFallback';

/**
 * Error boundary designed for wrapping route elements.
 * Auto-resets when the user navigates to a different path, so a crash on
 * one page doesn't permanently block other pages.
 */
export function RouteErrorBoundary({ children }: { children: ReactNode }) {
  const location = useLocation();

  return (
    <ErrorBoundary
      resetKey={location.pathname}
      fallback={(error) => <PageErrorFallback error={error} />}
    >
      {children}
    </ErrorBoundary>
  );
}
