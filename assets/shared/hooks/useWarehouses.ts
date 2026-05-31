/**
 * Shared warehouses hook.
 *
 * Fetches the list of warehouses accessible to the current user/branch
 * using TanStack React Query. Shared by both the POS Workspace and the
 * Administrative Sale Editor.
 *
 * The POS currently uses `usePosWarehouses` (a thin React Query wrapper).
 * That hook can be migrated to call this one directly.
 */

import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

export interface WarehouseOption {
  id: string;
  iri: string;
  name: string;
  branchId?: string | number;
}

interface UseWarehousesOptions {
  /**
   * Filter warehouses by branch ID.
   * When provided, only warehouses belonging to this branch are returned.
   */
  branchId?: string | number | null;
  /** Whether to skip the query (e.g. while branch ID is not yet known). */
  enabled?: boolean;
}

function buildWarehousesUrl(branchId?: string | number | null): string {
  const params = new URLSearchParams({ pagination: 'false' });
  if (branchId != null && branchId !== '') {
    params.set('branch', String(branchId));
  }
  return `${API}warehouses?${params.toString()}`;
}

function extractWarehouses(raw: unknown): WarehouseOption[] {
  let items: unknown[] = [];

  if (Array.isArray(raw)) {
    items = raw;
  } else if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>;
    if (Array.isArray(obj['hydra:member'])) items = obj['hydra:member'] as unknown[];
    else if (Array.isArray(obj['items'])) items = obj['items'] as unknown[];
    else if (Array.isArray(obj['data'])) items = obj['data'] as unknown[];
  }

  return items.map((w) => {
    const warehouse = w as Record<string, unknown>;
    const branch = warehouse['branch'] as Record<string, unknown> | undefined;
    return {
      id: String(warehouse['id'] ?? ''),
      iri: String(warehouse['@id'] ?? `${API}warehouses/${warehouse['id']}`),
      name: String(warehouse['name'] ?? ''),
      branchId: branch?.['id'] as string | number | undefined,
    };
  });
}

export interface UseWarehousesResult {
  warehouses: WarehouseOption[];
  isLoading: boolean;
  error: Error | null;
}

export function useWarehouses(options: UseWarehousesOptions = {}): UseWarehousesResult {
  const { branchId, enabled = true } = options;

  const { data, isLoading, error } = useQuery({
    queryKey: ['warehouses', branchId ?? null],
    queryFn: async () => {
      const response = await httpClient.get<unknown>(buildWarehousesUrl(branchId));
      return extractWarehouses(response.data);
    },
    enabled,
    staleTime: 5 * 60 * 1000, // 5 minutes — warehouse list changes rarely
  });

  return {
    warehouses: data ?? [],
    isLoading,
    error: error as Error | null,
  };
}
