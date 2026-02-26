import React, { lazy } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

const POSPage = lazy(() => import('@features/pos/pages/POSPage'));

export const FullScreenContent = () => {
  return (
    <Routes>
      <Route path="/pos" element={<POSPage />} />
      <Route path="*" element={<Navigate to="/pos" />} />
    </Routes>
  );
};
