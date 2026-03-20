import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';

const LoginPage = lazyNamed(() => import('@features/auth/pages/LoginPage'), 'LoginPage');
const InitialSetupPage = lazyNamed(
  () => import('@features/setup/pages/InitialSetupPage'),
  'InitialSetupPage',
);

export const UnauthenticatedContent = () => {
  return (
    <Routes>
      <Route path="/initial-setup" element={<InitialSetupPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="*" element={<Navigate to="/login" />} />
    </Routes>
  );
};
