import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export interface XReportDto {
  openingBalance: string;
  totalSales: string;
  cashIn: string;
  cashOut: string;
  currentBalance: string;
  shiftDurationMinutes: number;
  cashSalesNet?: string | null;
  cashAdjustmentsOut?: string | null;
  /** Sold in the shift and left on credit: not money yet, so it is in no payment method. */
  receivableTotal?: string;
  receivableOperations?: number;
  /** Of `cashOut`, what went to the safe or the bank rather than being spent. */
  withdrawalsOut?: string;
  /** Receipts issued in the shift. */
  documents?: { boletas: number; facturas: number; creditNotes: number };
  salesByPaymentMethod: Array<{
    paymentMethodId: number;
    paymentMethod: string;
    amount: string;
    /** Sales operations behind the amount (a sale paid with two methods counts once in each). */
    operations?: number;
  }>;
}

export interface CashRegisterClosureItem {
  cashSession: string;
  paymentMethod: string;
  expectedAmount: number;
  countedAmount: number | string | undefined;
  difference: number | undefined;
  note: unknown;
}

type CashRegisterMutationResponse = unknown;

const cashRegisterService = () => {
  const openCashRegister = (id: string, openingBalance: number) => {
    const amount = Number(openingBalance ?? 0);
    return httpClient.post<CashRegisterMutationResponse>(API + `cash-registers/${id}/open`, {
      id: Number(id),
      openingBalance: Number(amount.toFixed(2)),
    });
  };

  /** How the cash was counted and where it goes, recorded with the closing for its report. */
  const closeCashRegister = (
    id: string,
    closureData: CashRegisterClosureItem[],
    details: { denominations?: Record<string, number> | null; fundKept?: boolean | null } = {},
  ) => {
    return httpClient.post<CashRegisterMutationResponse>(
      API + `cash-sessions/${id}/close`,
      {
        closureItems: closureData,
        ...(details.denominations ? { denominations: details.denominations } : {}),
        ...(details.fundKept === null || details.fundKept === undefined
          ? {}
          : { fundKept: details.fundKept }),
      },
      { errorPresentation: 'inline' },
    );
  };

  const getMovements = (id: string) => {
    return httpClient.get<unknown>(API + `cash-registers/${id}/movements`);
  };

  const getXReport = (sessionId: string | number) => {
    return httpClient.get<XReportDto>(API + `cash-sessions/${sessionId}/x-report`, {
      errorPresentation: 'inline',
    });
  };

  return {
    openCashRegister,
    closeCashRegister,
    getMovements,
    getXReport,
  };
};

export default cashRegisterService;
