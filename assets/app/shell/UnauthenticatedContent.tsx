import React from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import { buildLoginRedirectSearch } from '@shared/lib/authRedirect';

const LoginPage = lazyNamed(() => import('@features/auth/LoginPage'), 'LoginPage');

export const UnauthenticatedContent = () => {
  const location = useLocation();
  const loginRedirectSearch = buildLoginRedirectSearch(location.pathname, location.search);

  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="*" element={<Navigate to={`/login${loginRedirectSearch}`} replace />} />
    </Routes>
  );
};
