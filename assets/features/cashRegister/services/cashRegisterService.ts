import { httpClient } from '../../../shared/api/HttpClient';
import { Constants } from '../../../core/common/Constants';

export interface XReportDto {
  openingBalance: string;
  totalSales: string;
  cashIn: string;
  cashOut: string;
  currentBalance: string;
  shiftDurationMinutes: number;
  salesByPaymentMethod: Array<{
    paymentMethodId: number;
    paymentMethod: string;
    amount: string;
  }>;
}

export interface CashMovementDto {
  id: number;
  type: 'income' | 'expense';
  amount: string;
  description: string;
  createdAt: string;
  category: { id: number; name: string } | null;
}

export interface CashMovementRequestDto {
  type: 'income' | 'expense';
  amount: string;
  categoryId?: number | null;
  description: string;
}

const cashRegisterService = () => {
  const openCashRegister = (id: string, openingBalance: number) => {
    const amount = Number(openingBalance ?? 0);
    return httpClient.post<any>(Constants.API_BASE_URL + `cash-registers/${id}/open`, {
      id: Number(id),
      openingBalance: Number(amount.toFixed(2)),
    });
  };

  const closeCashRegister = (id: string, closureData: any) => {
    return httpClient.post<any>(Constants.API_BASE_URL + `cash-registers/${id}/close`, {
      closureItems: closureData,
    });
  };

  /**
   * @deprecated Use getSessionMovements(cashSessionId) instead.
   * Kept for backwards compatibility if any other flow still references it.
   */
  const getMovements = (id: string) => {
    return httpClient.get<any>(Constants.API_BASE_URL + `cash-registers/${id}/movements`);
  };

  /** GET /api/cash-sessions/{cashSessionId}/movements */
  const getSessionMovements = (cashSessionId: number | string) => {
    return httpClient.get<CashMovementDto[]>(
      Constants.API_BASE_URL + `cash-sessions/${cashSessionId}/movements`,
    );
  };

  /** POST /api/cash-sessions/{cashSessionId}/movements */
  const createMovement = (cashSessionId: number | string, data: CashMovementRequestDto) => {
    return httpClient.post<CashMovementDto>(
      Constants.API_BASE_URL + `cash-sessions/${cashSessionId}/movements`,
      data,
    );
  };

  /** PUT /api/cash-sessions/{cashSessionId}/movements/{movId} */
  const updateMovement = (
    cashSessionId: number | string,
    movId: number,
    data: CashMovementRequestDto,
  ) => {
    return httpClient.put<CashMovementDto>(
      Constants.API_BASE_URL + `cash-sessions/${cashSessionId}/movements/${movId}`,
      data,
    );
  };

  /** DELETE /api/cash-sessions/{cashSessionId}/movements/{movId} */
  const deleteMovement = (cashSessionId: number | string, movId: number) => {
    return httpClient.delete<void>(
      Constants.API_BASE_URL + `cash-sessions/${cashSessionId}/movements/${movId}`,
    );
  };

  const getXReport = (cashSessionId: string | number) => {
    return httpClient.get<XReportDto>(
      Constants.API_BASE_URL + `cash-sessions/${cashSessionId}/x-report`,
    );
  };

  return {
    openCashRegister,
    closeCashRegister,
    /** @deprecated Use getSessionMovements instead */
    getMovements,
    getSessionMovements,
    createMovement,
    updateMovement,
    deleteMovement,
    getXReport,
  };
};

export default cashRegisterService;
