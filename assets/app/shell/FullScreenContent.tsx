import React, { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { FULLSCREEN_ROUTE_PATHS } from './routePaths';
import { RouteErrorBoundary } from '../../shared/components/RouteErrorBoundary';

const POSPage = lazy(() => import('@features/pos/POSPage'));

const FullScreenRouteLoading = () => (
  <div className="app-loading" role="status" aria-label="Cargando página">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

export const FullScreenContent = () => {
  return (
    <Routes>
      <Route
        path={FULLSCREEN_ROUTE_PATHS.pos}
        element={
          <RouteErrorBoundary>
            <Suspense fallback={<FullScreenRouteLoading />}>
              <POSPage />
            </Suspense>
          </RouteErrorBoundary>
        }
      />
      <Route path="*" element={<Navigate to="/pos" />} />
    </Routes>
  );
};
