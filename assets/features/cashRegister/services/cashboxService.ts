import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@core/common/Constants';

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
      Constants.API_BASE_URL + 'cash-registers/dashboard',
    );
  }
}

export const cashboxService = new CashboxService();
