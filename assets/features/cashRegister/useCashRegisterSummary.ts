import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

interface CashRegisterRow {
  id: number;
  status: string;
  todaySales?: number | string | null;
  balance?: number | string | null;
}

export interface CashRegisterSummary {
  totalRegisters: number;
  openRegisters: number;
  todaySalesTotal: number;
  balanceTotal: number;
}

async function fetchCashRegisterSummary(): Promise<CashRegisterSummary> {
  const rows = await httpRequestData(
    httpClient.get<CashRegisterRow[]>(`${API}cash-registers/dashboard`),
  );
  const registers = Array.isArray(rows) ? rows : [];

  return {
    totalRegisters: registers.length,
    openRegisters: registers.filter((r) => r.status === 'open').length,
    todaySalesTotal: registers.reduce((sum, r) => sum + Number(r.todaySales ?? 0), 0),
    balanceTotal: registers.reduce((sum, r) => sum + Number(r.balance ?? 0), 0),
  };
}

export function useCashRegisterSummary() {
  return useQuery({
    queryKey: ['cash-register-summary'],
    queryFn: fetchCashRegisterSummary,
    staleTime: 30_000,
  });
}
