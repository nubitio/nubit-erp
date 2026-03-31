import React, { lazy } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { RouteErrorBoundary } from '../../shared/components/RouteErrorBoundary';

const POSPage = lazy(() => import('@features/pos/pages/POSPage'));

export const FullScreenContent = () => {
  return (
    <Routes>
      <Route
        path="/pos"
        element={<RouteErrorBoundary><POSPage /></RouteErrorBoundary>}
      />
      <Route path="*" element={<Navigate to="/pos" />} />
    </Routes>
  );
};
