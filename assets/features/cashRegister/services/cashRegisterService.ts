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

  const getMovements = (id: string) => {
    return httpClient.get<any>(Constants.API_BASE_URL + `cash-registers/${id}/movements`);
  };

  const getXReport = (shiftId: string | number) => {
    return httpClient.get<XReportDto>(Constants.API_BASE_URL + `cash-shifts/${shiftId}/x-report`);
  };

  return {
    openCashRegister,
    closeCashRegister,
    getMovements,
    getXReport,
  };
};

export default cashRegisterService;
