import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { LoginPage } from './pages/Login/LoginPage';
import { InitialSetupPage } from './pages/InitialSetup/InitialSetupPage';

export const UnauthenticatedContent = () => {
  return (
    <Routes>
      <Route path="/initial-setup" element={<InitialSetupPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="*" element={<Navigate to="/login" />} />
    </Routes>
  );
};
