import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { ControlPlaneShell } from './shell/ControlPlaneShell';
import { DashboardPage } from './pages/DashboardPage';
import { AuditLogPage } from './pages/AuditLogPage';
import { PlanFeaturesPage } from './pages/PlanFeaturesPage';
import { PlansPage } from './pages/PlansPage';
import { TenantDetailPage } from './pages/TenantDetailPage';
import { TenantListPage } from './pages/TenantListPage';
import { UserCreatePage } from './pages/UserCreatePage';
import { UserEditPage } from './pages/UserEditPage';
import { UsersListPage } from './pages/UsersListPage';

export const ControlPlaneApp = () => (
  <BrowserRouter basename="/admin">
    <Routes>
      <Route element={<ControlPlaneShell />}>
        <Route index element={<DashboardPage />} />
        <Route path="tenants" element={<TenantListPage />} />
        <Route path="tenants/:id" element={<TenantDetailPage />} />
        <Route path="plans" element={<PlansPage />} />
        <Route path="plan-features" element={<PlanFeaturesPage />} />
        <Route path="audit-log" element={<AuditLogPage />} />
        <Route path="users" element={<UsersListPage />} />
        <Route path="users/new" element={<UserCreatePage />} />
        <Route path="users/:id" element={<UserEditPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  </BrowserRouter>
);
