import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import type { DataRecord } from '@nubitio/core';

export interface RuntimeCapability {
  enabled: boolean;
  config: DataRecord;
}

export interface TenantRuntimeConfig {
  profile: {
    preset: string;
    vertical: string;
    simpleStoreMode: boolean;
  };
  counts: {
    branches: number;
    warehouses: number;
  };
  defaults: {
    branch: { id: string; name: string | null } | null;
    warehouse: { id: string; name: string } | null;
  };
  capabilities: Record<string, RuntimeCapability>;
  settings: {
    requireCashRegister: boolean;
    autoInvoice: boolean;
    printMethod: string;
    defaultCurrency: string;
    negativeStockPolicy: string;
  };
  ui: {
    shell: {
      showBranchPicker: boolean;
    };
    inventory: {
      showWarehouseSelector: boolean;
      showWarehouseManagement: boolean;
      showTransfers: boolean;
      showLotFields: boolean;
    };
    reports: {
      showAdvancedReports: boolean;
      showExcelExport: boolean;
    };
  };
}

export const TENANT_RUNTIME_CONFIG_QUERY_KEY = ['tenant-runtime-config'] as const;

async function fetchTenantRuntimeConfig(): Promise<TenantRuntimeConfig> {
  return httpRequestData(
    httpClient.get<TenantRuntimeConfig>('/api/tenant/runtime-config'),
  );
}

export function useTenantRuntimeConfig() {
  return useQuery<TenantRuntimeConfig, Error>({
    queryKey: [...TENANT_RUNTIME_CONFIG_QUERY_KEY],
    queryFn: fetchTenantRuntimeConfig,
    staleTime: 5 * 60_000,
    refetchOnWindowFocus: true,
  });
}

export interface ApplyTenantPresetResponse {
  applied: {
    preset: string;
    version: number;
    defaultWarehouseCreated: boolean;
    notes: string[];
  };
  runtimeConfig: TenantRuntimeConfig;
}

async function applyTenantPreset(preset: string): Promise<ApplyTenantPresetResponse> {
  return httpRequestData(
    httpClient.post<ApplyTenantPresetResponse>('/api/tenant/apply-preset', { preset }),
  );
}

/**
 * Applies a vertical onboarding preset and primes the runtime-config cache with
 * the refreshed config returned by the server.
 */
export function useApplyTenantPreset() {
  const queryClient = useQueryClient();

  return useMutation<ApplyTenantPresetResponse, Error, string>({
    mutationFn: applyTenantPreset,
    onSuccess: (data) => {
      queryClient.setQueryData([...TENANT_RUNTIME_CONFIG_QUERY_KEY], data.runtimeConfig);
    },
  });
}
