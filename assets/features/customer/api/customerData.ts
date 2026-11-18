import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { ComboboxLoader } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { downloadBlob, filenameFromDisposition } from '@shared/lib/downloadBlob';
import {
  parseGridResponse,
  toGridParams,
  type FilterLeaf,
  type GridQuery,
} from '@shared/ui/data-grid/gridProtocol';
import { useGridQuery } from '@shared/ui/data-grid/useGridQuery';
import { priceListEndpoints } from '@features/catalog/api/priceListEndpoints';
import { customerApiUrl } from '../model/constants';
import type { Customer } from '../form/customerForm';
import type { toPayload } from '../form/customerForm';

export const CUSTOMERS_KEY = ['customers'] as const;

/** Columns the free-text search scans; stable identity so the query key does not churn. */
export const CUSTOMER_SEARCH_FIELDS = ['ruc', 'businessName', 'phone', 'email'] as const;

export type CustomerStatusFilter = '' | 'active' | 'inactive';

export function customerFilterOf(name: string, value: string): FilterLeaf | null {
  if (name !== 'status') return null;
  if (value === 'active') return ['isActive', '=', true];
  if (value === 'inactive') return ['isActive', '=', false];
  return null;
}

export function useCustomerGrid() {
  return useGridQuery<Customer>({
    url: customerApiUrl,
    queryKey: CUSTOMERS_KEY,
    searchFields: CUSTOMER_SEARCH_FIELDS,
    filterOf: customerFilterOf,
    initial: { sort: [{ selector: 'businessName', desc: false }] },
  });
}

export type CustomerPayload = ReturnType<typeof toPayload>;

/** Create (no id) or update (id). Errors stay on the form (422 → fields), so no global toast. */
export function useSaveCustomer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, payload }: { id?: number; payload: CustomerPayload }) =>
      id === undefined
        ? httpClient.post<Customer>(customerApiUrl, payload, { errorPresentation: 'inline' })
        : httpClient.patch<Customer>(`${customerApiUrl}/${id}`, payload, {
            errorPresentation: 'inline',
          }),
    // Not awaited on purpose: the save is already done, so the caller must not wait for the list to
    // reload before closing the form (a slow or failing reload would otherwise leave a saved form
    // open, inviting a second save).
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: CUSTOMERS_KEY });
    },
  });
}

export function useDeleteCustomer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      httpClient.delete(`${customerApiUrl}/${id}`, { errorPresentation: 'inline' }),
    // Not awaited on purpose: the save is already done, so the caller must not wait for the list to
    // reload before closing the form (a slow or failing reload would otherwise leave a saved form
    // open, inviting a second save).
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: CUSTOMERS_KEY });
    },
  });
}

const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

/** Every customer matching the current search/filters (no pagination), as a spreadsheet. */
export async function exportCustomers(exportParams: Record<string, unknown>): Promise<void> {
  const response = await httpClient.get<Blob>(customerApiUrl, {
    params: exportParams,
    headers: { Accept: XLSX },
    responseType: 'blob',
  });
  downloadBlob(response.data, filenameFromDisposition(response.headers, 'clientes.xlsx'));
}

/** Server-side search for the price-list selector. */
export const loadPriceLists: ComboboxLoader = async (query, { signal }) => {
  const gridQuery: GridQuery = {
    page: 0,
    pageSize: 20,
    search: query,
    searchFields: ['name'],
    sort: [{ selector: 'name', desc: false }],
    filter: [],
  };
  const response = await httpClient.get<unknown>(priceListEndpoints.list(), {
    params: toGridParams(gridQuery),
    signal,
    errorPresentation: 'inline',
  });
  const { rows } = parseGridResponse<{ '@id'?: string; id?: number; name?: string }>(
    response.data,
    response.headers,
  );

  return {
    options: rows
      .map((row) => {
        const iri =
          row['@id'] ??
          (row.id !== undefined ? `${priceListEndpoints.list()}/${row.id}` : undefined);
        return iri ? { value: iri, label: row.name ?? iri } : null;
      })
      .filter((option): option is { value: string; label: string } => option !== null),
  };
};
