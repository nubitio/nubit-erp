import { exceptionalFetch } from '@shared/api/network';
import type {
  CertificateRecord,
  TenantDetailPayload,
  TenantFeaturesPayload,
  TenantNotificationsPayload,
} from '../types';

export interface ControlPlaneSession {
  user: string | null;
  roles: string[];
}

export interface DashboardPayload {
  tenantStats: { total: number; active: number; suspended: number; deleted: number };
  planStats: { total_plans: number; tenants_without_plan: number };
  backupStats: {
    total_backups: number;
    failed_count: number;
    total_size_bytes: number;
    last_backup_at: string | null;
  };
  auditSummary: Array<{ action: string; count: number }>;
}

export interface TenantRecord {
  id: number;
  name: string;
  connection: string;
  status?: string;
  plan?: string | null;
  primary_domain?: string | null;
  deleted_at?: string | null;
}

export interface TenantsPayload {
  tenants: TenantRecord[];
  deletedTenants: TenantRecord[];
}

export interface PlanLimits {
  users: number;
  customers: number;
  products: number;
  invoices: number;
  branches: number;
  storage_mb: number;
}

export interface PlanRecord {
  id: number;
  name: string;
  limits: PlanLimits;
  is_default: boolean;
}

export interface PlansPayload {
  plans: PlanRecord[];
}

export interface AuditLogRecord {
  id: number;
  action: string;
  actor?: string | null;
  channel?: string | null;
  tenant_name?: string | null;
  metadata?: string;
  created_at: string;
}

export interface AuditLogPayload {
  logs: AuditLogRecord[];
  total: number;
  page: number;
  totalPages: number;
  limit: number;
  filters: { action: string; date_from: string; date_to: string };
  actions: string[];
}

export interface AdminUserRecord {
  id: number;
  username: string;
  roles: string[];
  is_active: boolean;
  last_login_at?: string | null;
  created_at?: string | null;
}

export interface AdminUsersPayload {
  users: AdminUserRecord[];
}

export interface PlanFeatureMatrixPayload {
  plans: PlanRecord[];
  featureKeys: string[];
  configFeatureKeys: string[];
  matrix: Record<string, Record<string, { isEnabled: boolean; configJson: Record<string, unknown> | null }>>;
}

async function cpFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const isFormData = init?.body instanceof FormData;
  const response = await exceptionalFetch(path, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(!isFormData && init?.body ? { 'Content-Type': 'application/json' } : {}),
      ...init?.headers,
    },
    ...init,
  }, 'api-documentation');

  if (response.status === 401 || response.status === 403) {
    window.location.href = '/admin/login';
    throw new Error('Unauthorized');
  }

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { error?: string };
    throw new Error(body.error ?? `Request failed (${response.status})`);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

const tenantPath = (id: number) => `/api/control-plane/tenants/${id}`;

export const controlPlaneClient = {
  getSession: () => cpFetch<ControlPlaneSession>('/api/control-plane/session'),
  getDashboard: () => cpFetch<DashboardPayload>('/api/control-plane/dashboard'),
  getTenants: () => cpFetch<TenantsPayload>('/api/control-plane/tenants'),
  createTenant: (payload: { name: string; connection: string }) =>
    cpFetch<{ ok: boolean }>('/api/control-plane/tenants', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  deleteTenant: (id: number) =>
    cpFetch<{ ok: boolean }>(tenantPath(id), { method: 'DELETE' }),
  restoreTenant: (id: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/restore`, { method: 'POST' }),
  purgeTenant: (id: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/purge`, { method: 'POST' }),
  getPlans: () => cpFetch<PlansPayload>('/api/control-plane/plans'),
  createPlan: (payload: {
    name: string;
    limits: PlanLimits;
    isDefault: boolean;
  }) =>
    cpFetch<{ ok: boolean }>('/api/control-plane/plans', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  updatePlan: (
    id: number,
    payload: { name: string; limits: PlanLimits; isDefault: boolean },
  ) =>
    cpFetch<{ ok: boolean }>(`/api/control-plane/plans/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  deletePlan: (id: number) =>
    cpFetch<{ ok: boolean }>(`/api/control-plane/plans/${id}`, { method: 'DELETE' }),
  getAuditLog: (params: URLSearchParams) =>
    cpFetch<AuditLogPayload>(`/api/control-plane/audit-log?${params.toString()}`),
  getAdminUsers: () => cpFetch<AdminUsersPayload>('/api/control-plane/users'),
  getAdminUser: (id: number) => cpFetch<{ user: AdminUserRecord }>(`/api/control-plane/users/${id}`),
  createAdminUser: (payload: {
    username: string;
    password: string;
    confirmPassword: string;
    roles: string[];
  }) =>
    cpFetch<{ ok: boolean; id: number }>('/api/control-plane/users', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  updateAdminUser: (
    id: number,
    payload: { roles: string[]; isActive: boolean },
  ) =>
    cpFetch<{ ok: boolean }>(`/api/control-plane/users/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  toggleAdminUser: (id: number) =>
    cpFetch<{ ok: boolean; is_active: boolean }>(`/api/control-plane/users/${id}/toggle`, {
      method: 'POST',
    }),
  resetAdminUserPassword: (
    id: number,
    payload: { password: string; confirmPassword: string },
  ) =>
    cpFetch<{ ok: boolean }>(`/api/control-plane/users/${id}/reset-password`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  getTenantDetail: (id: number) => cpFetch<TenantDetailPayload>(tenantPath(id)),
  updateTenant: (id: number, payload: { name: string; connection: string }) =>
    cpFetch<{ ok: boolean }>(tenantPath(id), { method: 'PATCH', body: JSON.stringify(payload) }),
  changePlan: (id: number, plan: string) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/plan`, {
      method: 'POST',
      body: JSON.stringify({ plan }),
    }),
  suspendTenant: (id: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/suspend`, { method: 'POST' }),
  unsuspendTenant: (id: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/unsuspend`, { method: 'POST' }),
  impersonateTenantUser: (id: number, username: string) =>
    cpFetch<{ ok: boolean; token: string; expiresAt: number; user: Record<string, unknown> }>(
      `${tenantPath(id)}/impersonate`,
      { method: 'POST', body: JSON.stringify({ username }) },
    ),
  addDomain: (id: number, domain: string, isPrimary: boolean) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/domains`, {
      method: 'POST',
      body: JSON.stringify({ domain, isPrimary }),
    }),
  setPrimaryDomain: (tenantId: number, domainId: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(tenantId)}/domains/${domainId}/primary`, {
      method: 'POST',
    }),
  deleteDomain: (tenantId: number, domainId: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(tenantId)}/domains/${domainId}`, { method: 'DELETE' }),
  provisionTenant: (
    id: number,
    payload: { adminUser: string; adminPassword: string; domain?: string },
  ) =>
    cpFetch<{ ok: boolean; provisionId?: number }>(`${tenantPath(id)}/provision`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  updateBackupSchedule: (
    id: number,
    payload: { backupFrequency: number; backupRetentionDays: number },
  ) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/backup-schedule`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  queueBackup: (id: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/backups`, { method: 'POST' }),
  restoreBackup: (id: number, backupId: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/backups/${backupId}/restore`, { method: 'POST' }),
  deleteBackup: (id: number, backupId: number) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/backups/${backupId}`, { method: 'DELETE' }),
  backupDownloadUrl: (id: number, backupId: number) =>
    `${tenantPath(id)}/backups/${backupId}/download`,
  getCertificateDetail: (tenantId: number, certificateId: number) =>
    cpFetch<{ certificate: CertificateRecord }>(
      `${tenantPath(tenantId)}/certificates/${certificateId}`,
    ),
  uploadCertificate: (id: number, formData: FormData) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/certificates`, {
      method: 'POST',
      body: formData,
    }),
  revokeCertificate: (id: number, ruc: string, reason: string) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/certificates/${ruc}/revoke`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    }),
  getTenantNotifications: (id: number) =>
    cpFetch<TenantNotificationsPayload>(`${tenantPath(id)}/notifications`),
  updateTenantNotifications: (id: number, matrix: TenantNotificationsPayload['matrix']) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/notifications`, {
      method: 'PUT',
      body: JSON.stringify({ matrix }),
    }),
  testTenantNotification: (id: number, eventKey: string, channel: string) =>
    cpFetch<{ ok: boolean; sent: boolean; message: string }>(`${tenantPath(id)}/notifications/test`, {
      method: 'POST',
      body: JSON.stringify({ eventKey, channel }),
    }),
  getTenantFeatures: (id: number) =>
    cpFetch<TenantFeaturesPayload>(`${tenantPath(id)}/features`),
  createTenantFeature: (
    id: number,
    payload: {
      featureKey: string;
      isEnabled: boolean;
      configJson?: string;
      expiresAt?: string;
      reason?: string;
    },
  ) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/features`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  updateTenantFeature: (
    id: number,
    key: string,
    payload: {
      isEnabled: boolean;
      configJson?: string;
      expiresAt?: string;
      reason?: string;
    },
  ) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/features/${key}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  deleteTenantFeature: (id: number, key: string) =>
    cpFetch<{ ok: boolean }>(`${tenantPath(id)}/features/${key}`, { method: 'DELETE' }),
  getPlanFeatures: () => cpFetch<PlanFeatureMatrixPayload>('/api/control-plane/plan-features'),
  updatePlanFeature: (
    planName: string,
    featureKey: string,
    payload: { isEnabled: boolean; configJson?: string | Record<string, unknown> | null },
  ) =>
    cpFetch<{ ok: boolean }>(`/api/control-plane/plan-features/${planName}/${featureKey}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
};
