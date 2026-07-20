import React, { Suspense } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import { buildLoginRedirectSearch } from '@shared/lib/authRedirect';

const LoginPage = lazyNamed(() => import('@features/auth/LoginPage'), 'LoginPage');
const ImpersonateBridgePage = lazyNamed(
  () => import('@features/auth/ImpersonateBridgePage'),
  'ImpersonateBridgePage',
);

const AuthRouteLoading = () => (
  <div className="app-loading" role="status" aria-label="Cargando login">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

export const UnauthenticatedContent = () => {
  const location = useLocation();
  const loginRedirectSearch = buildLoginRedirectSearch(location.pathname, location.search);

  return (
    <Routes>
      <Route
        path="/login"
        element={(
          <Suspense fallback={<AuthRouteLoading />}>
            <LoginPage />
          </Suspense>
        )}
      />
      <Route
        path="/impersonate"
        element={(
          <Suspense fallback={<AuthRouteLoading />}>
            <ImpersonateBridgePage />
          </Suspense>
        )}
      />
      <Route path="*" element={<Navigate to={`/login${loginRedirectSearch}`} replace />} />
    </Routes>
  );
};
