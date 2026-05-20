import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';

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
      API_BASE_URL + 'cash-registers/dashboard',
    );
  }
}

export const cashboxService = new CashboxService();
