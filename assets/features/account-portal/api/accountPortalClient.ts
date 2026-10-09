import { exceptionalFetch } from '@shared/api/network';

export interface AccountSession {
  authenticated: boolean;
  account?: { id: number; email: string };
  pendingEmail?: string | null;
}

export interface PortalConfig {
  domainSuffix: string;
}

export interface TenantRecord {
  id: number;
  name: string;
  status: string;
  plan: string | null;
  primary_domain: string | null;
  role?: string;
  created_at?: string;
}

export interface ProvisionRecord {
  id: number;
  tenant_id: number | null;
  tenant_name: string;
  domain: string;
  plan: string;
  status: string;
  error_message?: string | null;
  tenant_url?: string;
}

export interface SubscriptionRecord {
  id: number;
  plan: string;
  billing_cycle: string;
  gateway: string;
  status: string;
  current_period_end?: string | null;
}

export interface SubdomainCheck {
  available: boolean;
  reason: string;
  normalized: string;
  domain: string;
}

export interface ProvisionStatus {
  status: string;
  tenantName: string;
  domain: string;
  error: string;
  tenantUrl: string;
}

async function apFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await exceptionalFetch(
    path,
    {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
        ...init?.headers,
      },
      ...init,
    },
    'api-documentation',
  );

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { error?: string };
    throw new Error(body.error ?? `Request failed (${response.status})`);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export const accountPortalClient = {
  getConfig: () => apFetch<PortalConfig>('/api/account/config'),
  getSession: () => apFetch<AccountSession>('/api/account/session'),
  register: (email: string, password: string) =>
    apFetch<{ ok: boolean; email: string }>('/api/account/register', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
  verify: (email: string, code: string) =>
    apFetch<{ ok: boolean; accountId: number }>('/api/account/verify', {
      method: 'POST',
      body: JSON.stringify({ email, code }),
    }),
  login: (email: string, password: string) =>
    apFetch<{ ok: boolean }>('/api/account/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
  logout: () => apFetch<{ ok: boolean }>('/api/account/logout', { method: 'POST' }),
  getDashboard: () =>
    apFetch<{
      account: { id: number; email: string };
      tenants: TenantRecord[];
      provisions: ProvisionRecord[];
    }>('/api/account/dashboard'),
  dismissProvision: (provisionId: number) =>
    apFetch<{ ok: boolean }>(`/api/account/provisions/${provisionId}/dismiss`, { method: 'POST' }),
  checkSubdomain: (subdomain: string) =>
    apFetch<SubdomainCheck>(
      `/api/account/subdomains/check?subdomain=${encodeURIComponent(subdomain)}`,
    ),
  provision: (payload: {
    tenantName: string;
    subdomain: string;
    plan: string;
    gateway: string;
    billingCycle: string;
    adminPassword: string;
  }) =>
    apFetch<{
      token: string;
      provisionId: number;
      requiresPayment: boolean;
      approvalUrl?: string;
    }>('/api/account/provisions', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  getProvisionStatus: (token: string) =>
    apFetch<ProvisionStatus>(`/api/account/provisions/${encodeURIComponent(token)}/status`),
  getTenant: (tenantId: number) =>
    apFetch<{ tenant: TenantRecord; subscription: SubscriptionRecord | null }>(
      `/api/account/tenants/${tenantId}`,
    ),
  changePlan: (
    tenantId: number,
    payload: { plan: string; gateway: string; billingCycle: string },
  ) =>
    apFetch<{ ok: boolean; approvalUrl?: string; message?: string }>(
      `/api/account/tenants/${tenantId}/change-plan`,
      { method: 'POST', body: JSON.stringify(payload) },
    ),
  cancelSubscription: (tenantId: number) =>
    apFetch<{ ok: boolean; message: string }>(
      `/api/account/tenants/${tenantId}/cancel-subscription`,
      {
        method: 'POST',
      },
    ),
};
