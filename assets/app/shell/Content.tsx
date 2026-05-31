import React, { Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { appRoutes } from './app-routes';
import { EfactAdminShell } from './EfactAdminShell';
import { userInformation } from '@app/userInformation';
import { HydraResourceSchemaProvider, HydraResourceStoreProvider, SchemaProvider } from '@nubit/hydra';
import { RouteErrorBoundary } from '@shared/components/RouteErrorBoundary';

const RouteLoading = () => (
  <div className="app-route-loading" role="status" aria-label="Cargando página">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

export const Content = () => {
  const defaultPath = userInformation().currentUserRoleId === 3 ? '/pos' : '/dashboard';

  return (
    <SchemaProvider>
      <HydraResourceSchemaProvider>
        <HydraResourceStoreProvider>
          <EfactAdminShell title={userInformation().currentBranchName}>
            <Routes>
              {appRoutes.map(({ path, element: RouteComponent }) => (
                <Route
                  key={path}
                  path={path}
                  element={(
                    <RouteErrorBoundary>
                      <Suspense fallback={<RouteLoading />}>
                        <RouteComponent />
                      </Suspense>
                    </RouteErrorBoundary>
                  )}
                />
              ))}
              <Route path="*" element={<Navigate to={defaultPath} />} />
            </Routes>
          </EfactAdminShell>
        </HydraResourceStoreProvider>
      </HydraResourceSchemaProvider>
    </SchemaProvider>
  );
};
