import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { lazyNamed } from './routes/lazy';

const LoginPage = lazyNamed(() => import('./pages/Login/LoginPage'), 'LoginPage');
const InitialSetupPage = lazyNamed(
  () => import('./pages/InitialSetup/InitialSetupPage'),
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
