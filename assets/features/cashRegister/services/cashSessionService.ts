import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

/** The cash session the signed-in user has open. Sales are tied to it, not to the register. */
export interface CashSessionInfo {
  id: number;
  cashRegisterId: number;
  cashRegisterName: string;
  openedAt: string;
  openingAmount: string;
}

export interface CashRegisterOption {
  id: number;
  name: string;
}

/** A register of the branch that somebody else has open. */
export interface CashBusyRegister {
  id: number;
  name: string;
  /** Username of whoever opened it; null if that user no longer exists. */
  cashier: string | null;
  openedAt: string;
}

export interface CashPosState {
  session: CashSessionInfo | null;
  /** Registers of the current branch with no open session; empty while the user has one open. */
  availableRegisters: CashRegisterOption[];
  /** Registers open by someone else; only filled while the user has no session of their own. */
  busyRegisters?: CashBusyRegister[];
}

export interface CashMovementCategoryOption {
  id: number;
  name: string;
  type: 'income' | 'expense';
}

export interface CashSessionMovement {
  id: number;
  type: 'income' | 'expense';
  amount: string;
  description: string;
  createdAt?: string;
  category?: { id: number; name: string; type: 'income' | 'expense' } | null;
}

export interface CashShiftSale {
  id: string;
  issuedAt?: string | null;
  /** `VOIDED` sales are listed but do not count towards the shift. */
  status: string;
  total: string;
  documentType?: string | null;
  /** Series and number of the receipt, e.g. `B001-12`; null while the sale has none. */
  documentNumber?: string | null;
  sunatStatus?: string | null;
  payments: { method: string; amount: string }[];
}

/** The sales of the shift the signed-in user has open, newest first. */
export interface CashShiftSales {
  sessionId: number | null;
  /** Sales that count (not voided) and their sum. */
  count: number;
  total: string;
  sales: CashShiftSale[];
}

export const cashSessionService = {
  getPosState: () =>
    httpClient
      .get<CashPosState>(API + 'cash-pos-state', { errorPresentation: 'inline' })
      .then((response) => response.data),

  getShiftSales: () =>
    httpClient
      .get<CashShiftSales>(API + 'cash-pos-state/sales', { errorPresentation: 'inline' })
      .then((response) => response.data),

  open: (cashRegisterId: number, openingBalance: number) =>
    httpClient.post(
      API + `cash-registers/${cashRegisterId}/open`,
      { id: cashRegisterId, openingBalance: Number(openingBalance.toFixed(2)) },
      { errorPresentation: 'inline' },
    ),

  getMovements: (sessionId: number | string) =>
    httpClient
      .get<CashSessionMovement[]>(API + `cash-sessions/${sessionId}/movements`, {
        errorPresentation: 'inline',
      })
      .then((response) => response.data),

  getMovementCategories: (): Promise<CashMovementCategoryOption[]> =>
    httpClient
      .get<(CashMovementCategoryOption & { active: boolean })[]>(API + 'cash-movement-categories', {
        errorPresentation: 'inline',
      })
      .then((response) => response.data.filter((category) => category.active)),

  addMovement: (input: {
    sessionId: number;
    categoryId: number;
    amount: number;
    description: string;
  }) =>
    httpClient.post(
      API + 'cash-movements',
      {
        cashSession: API + `cash-sessions/${input.sessionId}`,
        category: API + `cash-movement-categories/${input.categoryId}`,
        amount: input.amount.toFixed(2),
        description: input.description,
      },
      { errorPresentation: 'inline' },
    ),
};
