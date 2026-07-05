import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { DashboardPage } from './pages/DashboardPage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { StatusPage } from './pages/StatusPage';
import { TenantDetailPage } from './pages/TenantDetailPage';
import { VerifyPage } from './pages/VerifyPage';
import { WizardPage } from './pages/WizardPage';

export const AccountPortalApp = () => (
  <BrowserRouter>
    <Routes>
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/register/verify" element={<VerifyPage />} />
      <Route path="/register/new-tenant" element={<WizardPage />} />
      <Route path="/register/status/:token" element={<StatusPage />} />
      <Route path="/account/login" element={<LoginPage />} />
      <Route path="/account/dashboard" element={<DashboardPage />} />
      <Route path="/account/tenant/:tenantId" element={<TenantDetailPage />} />
      <Route path="/" element={<Navigate to="/register" replace />} />
      <Route path="*" element={<Navigate to="/register" replace />} />
    </Routes>
  </BrowserRouter>
);