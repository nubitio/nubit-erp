import React, { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from '@app/contexts/auth';
import { SettingsDialog } from '@app/profile';
import { useWorkspaceMenu } from '@features/navigation';
import { FULLSCREEN_ROUTE_PATHS } from './routePaths';
import { posExitPath } from './components/workspaceNavigation';
import { RouteErrorBoundary } from '../../shared/components/RouteErrorBoundary';

const POSPage = lazy(() => import('@features/pos/POSPage'));

const FullScreenRouteLoading = () => (
  <div className="app-loading" role="status" aria-label="Cargando página">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

export const FullScreenContent = () => {
  const { signOut } = useAuth();
  const menu = useWorkspaceMenu();
  // Unknown while the menu loads: no button yet, rather than one that vanishes for a cashier.
  const exitTo = menu.isLoading ? null : posExitPath(menu.items);

  return (
    <>
      <Routes>
        <Route
          path={FULLSCREEN_ROUTE_PATHS.pos}
          element={
            <RouteErrorBoundary>
              <Suspense fallback={<FullScreenRouteLoading />}>
                <POSPage onSignOut={signOut} exitTo={exitTo} />
              </Suspense>
            </RouteErrorBoundary>
          }
        />
        <Route path="*" element={<Navigate to="/pos" />} />
      </Routes>
      {/* The shell mounts it for the other pages; the POS lives outside the shell. */}
      <SettingsDialog />
    </>
  );
};
