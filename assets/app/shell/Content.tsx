import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { appRoutes } from './app-routes';
import { EfactAdminShell } from './EfactAdminShell';
import { userInformation } from '@core/common/UserInformation';
import { SchemaProvider } from '@nubit/api-platform';
import { RouteErrorBoundary } from '@shared/components/RouteErrorBoundary';

export const Content = () => {
  const defaultPath = userInformation().currentUserRoleId === 3 ? '/pos' : '/dashboard';

  return (
    <SchemaProvider>
      <EfactAdminShell title={userInformation().currentBranchName}>
        <Routes>
          {appRoutes.map(({ path, element: RouteComponent }) => (
            <Route
              key={path}
              path={path}
              element={(
                <RouteErrorBoundary>
                  <RouteComponent />
                </RouteErrorBoundary>
              )}
            />
          ))}
          <Route path="*" element={<Navigate to={defaultPath} />} />
        </Routes>
      </EfactAdminShell>
    </SchemaProvider>
  );
};
