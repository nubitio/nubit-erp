import React, { Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { appRoutes } from './app-routes';
import { EfactAdminShell } from './EfactAdminShell';
import { userInformation } from '@app/userInformation';
import { HydraResourceSchemaProvider, HydraResourceStoreProvider, SchemaProvider } from '@nubitio/hydra';
import { RouteErrorBoundary } from '@shared/components/RouteErrorBoundary';
import type { AppRouteDefinition } from '@app/router/types';

const RouteLoading = () => (
  <div className="app-route-loading" role="status" aria-label="Cargando página">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

function wrapRouteElement(RouteComponent: AppRouteDefinition['element']) {
  return (
    <RouteErrorBoundary>
      <Suspense fallback={<RouteLoading />}>
        <RouteComponent />
      </Suspense>
    </RouteErrorBoundary>
  );
}

function renderRouteTree(routes: AppRouteDefinition[]): React.ReactNode {
  return routes.map(({ path, element: RouteComponent, children }) => (
    <Route key={path} path={path} element={wrapRouteElement(RouteComponent)}>
      {children ? renderRouteTree(children) : null}
    </Route>
  ));
}

export const Content = () => {
  const defaultPath = userInformation().currentUserRoleId === 3 ? '/pos' : '/dashboard';

  return (
    <SchemaProvider>
      <HydraResourceSchemaProvider>
        <HydraResourceStoreProvider>
          <EfactAdminShell title={userInformation().currentBranchName}>
            <Routes>
              {renderRouteTree(appRoutes)}
              <Route path="*" element={<Navigate to={defaultPath} />} />
            </Routes>
          </EfactAdminShell>
        </HydraResourceStoreProvider>
      </HydraResourceSchemaProvider>
    </SchemaProvider>
  );
};
