import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export interface CashRegisterDashboardDto {
  id: number;
  name: string;
  cashSessionId: number | null;
  /** @deprecated Use cashSessionId. Kept while legacy consumers are removed. */
  cashShiftId: number | null;
  openedAt: string | null;
  closedAt: string | null;
  cashier: string | null;
  balance: number;
  status: 'open' | 'closed';
  todaySales: string;
  /** When the register's latest closed shift closed; null if it never closed one. */
  lastClosedAt?: string | null;
  /** Counted minus expected of that closing; null if it was not counted. */
  lastDifference?: string | null;
}

/** The branch's cash figures for the header of "Cajas y turnos". */
export interface CashRegisterSummaryDto {
  /** Sold today in the branch's shifts, whatever the payment method. */
  salesToday: string;
  registersWithShiftToday: number;
  /** Net of the closing differences of the last 7 days; negative is missing cash. */
  closureDifference7Days: string;
  shortageShifts7Days: number;
  /** Every shift the branch has had, for the paging of the history. */
  shiftsTotal: number;
}

/** A shift of the branch with what it sold, for the recent-shifts table. */
export interface CashSessionRecentDto {
  id: number;
  cashRegisterId: number;
  cashRegisterName: string;
  cashier: string | null;
  openedAt: string;
  closedAt: string | null;
  status: 'open' | 'closed';
  openingAmount: string;
  sales: string;
  /** Counted minus expected; null while open or if it was never counted. */
  difference: string | null;
}

class CashboxService {
  getDashboard() {
    return httpClient.get<CashRegisterDashboardDto[]>(API + 'cash-registers/dashboard');
  }

  getSummary(cashRegisterId?: number) {
    const query = cashRegisterId ? `?cashRegisterId=${cashRegisterId}` : '';
    return httpClient
      .get<CashRegisterSummaryDto>(API + `cash-registers/summary${query}`, {
        errorPresentation: 'inline',
      })
      .then((response) => response.data);
  }

  getRecentSessions(limit = 10, page = 1, cashRegisterId?: number) {
    return httpClient
      .get<CashSessionRecentDto[]>(
        API +
          `cash-sessions/recent?limit=${limit}&page=${page}${
            cashRegisterId ? `&cashRegisterId=${cashRegisterId}` : ''
          }`,
        {
          errorPresentation: 'inline',
        },
      )
      .then((response) => response.data);
  }
}

export const cashboxService = new CashboxService();
