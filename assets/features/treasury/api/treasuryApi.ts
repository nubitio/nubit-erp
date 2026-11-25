import { getCoreApiBaseUrl } from '@nubitio/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { TENANT_RUNTIME_CONFIG_QUERY_KEY } from '@shared/hooks/useTenantRuntimeConfig';

const API = `${getCoreApiBaseUrl()}treasury`;

export type AccountKind = 'cash_register' | 'bank' | 'wallet' | 'clearing' | 'suspense';

export interface TreasuryAccount {
  id: string;
  kind: AccountKind;
  name: string;
  currency: string;
  branchId: string | null;
  cashRegisterId: number | null;
  bankAccountId: string | null;
  active: boolean;
  /** Decimal string, e.g. "1250.00". Never do arithmetic on it with floats. */
  balance: string;
}

export interface TreasuryPosition {
  accounts: TreasuryAccount[];
  /** One total per currency; currencies are never added together. */
  totalsByCurrency: Record<string, string>;
}

export interface PaymentMethodRef {
  id: number;
  name: string;
  isCash: boolean;
  settlesImmediately: boolean;
}

export interface MethodAccountMapping {
  branchId: string;
  paymentMethodId: number;
  currency: string;
  accountId: string;
}

export interface MappingData {
  branches: Array<{ id: string; name: string }>;
  paymentMethods: PaymentMethodRef[];
  mappings: MethodAccountMapping[];
}

export interface OpenItemsSummary {
  balance: string;
  overdue: string;
  dueWithinSevenDays: string;
  dueWithinThirtyDays: string;
  count: number;
  overdueCount: number;
}

export interface CurrencySummary {
  currency: string;
  position: {
    total: string;
    cash: string;
    banks: string;
    wallets: string;
    clearing: string;
    suspense: string;
  };
  receivables: OpenItemsSummary;
  payables: OpenItemsSummary;
  /** Today's money, plus receivables due within 30 days, minus payables due within 30 days or overdue. */
  projectedInThirtyDays: string;
}

export interface FinanceSummary {
  asOf: string;
  currencies: CurrencySummary[];
  attention: {
    unreconciledBankMovements: number;
    suspense: Array<{ currency: string; balance: string }>;
    accountsWithoutOpeningBalance: string[];
  };
}

const POSITION_KEY = ['treasury', 'accounts'] as const;
const SUMMARY_KEY = ['treasury', 'summary'] as const;
const MAPPING_KEY = ['treasury', 'mapping'] as const;

/** The server's own explanation (`detail`) when it gave one, else `fallback`. */
export function treasuryErrorMessage(error: unknown, fallback: string): string {
  const data = (error as { data?: { detail?: unknown; message?: unknown } } | null)?.data;
  for (const value of [data?.detail, data?.message]) {
    if (typeof value === 'string' && value.trim() !== '') return value;
  }

  return fallback;
}

export function useTreasuryPosition(enabled = true) {
  return useQuery({
    queryKey: POSITION_KEY,
    enabled,
    queryFn: async ({ signal }) =>
      (await httpClient.get<TreasuryPosition>(`${API}/accounts`, { signal })).data,
  });
}

export function useFinanceSummary(enabled = true) {
  return useQuery({
    queryKey: SUMMARY_KEY,
    enabled,
    queryFn: async ({ signal }) =>
      (await httpClient.get<FinanceSummary>(`${API}/summary`, { signal })).data,
  });
}

export function usePaymentMethodMapping(enabled = true) {
  return useQuery({
    queryKey: MAPPING_KEY,
    enabled,
    queryFn: async ({ signal }) =>
      (await httpClient.get<MappingData>(`${API}/payment-method-accounts`, { signal })).data,
  });
}

function useInvalidate() {
  const client = useQueryClient();

  return () => client.invalidateQueries({ queryKey: ['treasury'] });
}

/**
 * Switches Treasury on (the accounts the tenant already has are created) or off (nothing is deleted).
 * The runtime config is refreshed so menus and screens follow the new state at once.
 */
export function useSetTreasuryCapability() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (enabled: boolean) =>
      (
        await httpClient.put<{ enabled: boolean; created: string[]; mapped: number }>(
          `${API}/capability`,
          { enabled },
        )
      ).data,
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: TENANT_RUNTIME_CONFIG_QUERY_KEY });
      void client.invalidateQueries({ queryKey: ['treasury'] });
    },
  });
}

export function useSyncAccounts() {
  const invalidate = useInvalidate();

  return useMutation({
    mutationFn: async () =>
      (await httpClient.post<{ created: string[]; mapped: number }>(`${API}/accounts/sync`, {}))
        .data,
    onSuccess: invalidate,
  });
}

export function useCreateTreasuryAccount() {
  const invalidate = useInvalidate();

  return useMutation({
    mutationFn: async (input: {
      name: string;
      currency: string;
      kind: 'wallet' | 'clearing';
      branchId?: string | null;
    }) => (await httpClient.post<TreasuryAccount>(`${API}/accounts`, input)).data,
    onSuccess: invalidate,
  });
}

export function useSetOpeningBalance() {
  const invalidate = useInvalidate();

  return useMutation({
    mutationFn: async (input: { accountId: string; amount: string; date?: string }) =>
      (
        await httpClient.post<{ movementId: string }>(
          `${API}/accounts/${input.accountId}/opening-balance`,
          { amount: input.amount, date: input.date },
        )
      ).data,
    onSuccess: invalidate,
  });
}

export function useTransfer() {
  const invalidate = useInvalidate();

  return useMutation({
    mutationFn: async (input: {
      fromAccountId: string;
      toAccountId: string;
      amount: string;
      date?: string;
      reference?: string;
    }) => (await httpClient.post<{ transferId: string }>(`${API}/transfers`, input)).data,
    onSuccess: invalidate,
  });
}

export function useSetMapping() {
  const invalidate = useInvalidate();

  return useMutation({
    mutationFn: async (input: {
      branchId: string;
      paymentMethodId: number;
      currency: string;
      accountId: string | null;
    }) => (await httpClient.put<{ mapped: boolean }>(`${API}/payment-method-accounts`, input)).data,
    onSuccess: invalidate,
  });
}
