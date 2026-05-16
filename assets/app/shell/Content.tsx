import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { appRoutes } from './app-routes';
import { SideNavOuterToolbar as SideNavBarLayout } from '../../layouts';
import { userInformation } from '../../core/common/UserInformation';
import { SchemaProvider } from '../../core/crud/hydra/SchemaContext';
import { RouteErrorBoundary } from '../../shared/components/RouteErrorBoundary';

export const Content = () => {
  const defaultPath = userInformation().currentUserRoleId === 3 ? '/pos' : '/dashboard';

  return (
    <SchemaProvider>
      <SideNavBarLayout title={userInformation().currentBranchName}>
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
      </SideNavBarLayout>
    </SchemaProvider>
  );
};
