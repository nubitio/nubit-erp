import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

export interface CashRegisterDashboardDto {
  id: number;
  name: string;
  cashShiftId: number | null;
  openedAt: string | null;
  closedAt: string | null;
  cashier: string | null;
  balance: number;
  status: 'open' | 'closed';
  todaySales: string;
}

class CashboxService {
  getDashboard() {
    return httpClient.get<CashRegisterDashboardDto[]>(
      API + 'cash-registers/dashboard',
    );
  }
}

export const cashboxService = new CashboxService();
