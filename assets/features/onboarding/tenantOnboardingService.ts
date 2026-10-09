import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';

export interface OnboardingCompany {
  id: string | null;
  ruc: string;
  businessName: string;
  tradeName: string | null;
  fiscalAddress: string;
  phone: string | null;
}

export interface OnboardingBranch {
  id: string | null;
  name: string | null;
  address: string | null;
  phone: string | null;
}

export interface OnboardingWarehouse {
  id: string | null;
  name: string | null;
  branchId: string | null;
}

export interface TenantOnboardingSnapshot {
  company: OnboardingCompany | null;
  branch: OnboardingBranch | null;
  warehouse: OnboardingWarehouse | null;
}

export interface SaveCompanyInput {
  ruc: string;
  businessName: string;
  tradeName?: string;
  fiscalAddress: string;
  phone?: string;
}

export interface SaveBranchInput {
  name: string;
  address?: string;
  invoiceSeries: string;
  receiptSeries: string;
}

export interface SaveWarehouseInput {
  name: string;
}

export const tenantOnboardingService = {
  snapshot() {
    return httpRequestData(
      httpClient.get<TenantOnboardingSnapshot>('/api/tenant/onboarding/snapshot'),
    );
  },
  saveCompany(input: SaveCompanyInput) {
    return httpRequestData(
      httpClient.post<{ company: OnboardingCompany }>('/api/tenant/onboarding/company', input),
    );
  },
  saveBranch(input: SaveBranchInput) {
    return httpRequestData(
      httpClient.post<{ branch: OnboardingBranch }>('/api/tenant/onboarding/branch', input),
    );
  },
  saveWarehouse(input: SaveWarehouseInput) {
    return httpRequestData(
      httpClient.post<{ warehouse: OnboardingWarehouse }>(
        '/api/tenant/onboarding/warehouse',
        input,
      ),
    );
  },
};
