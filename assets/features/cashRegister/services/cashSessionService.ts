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

/** What a person registers by hand: money in, money out, or cash taken to the safe. */
export type CashMovementKind = 'income' | 'expense' | 'withdrawal';

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
  /** What was left on credit when it was rung up (not money yet); "0.00" if none. */
  receivable: string;
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
  /** What the shift sold, credit included. */
  total: string;
  sales: CashShiftSale[];
  /** The same sales by the way they were paid; credit is in `receivable`, apart from the methods. */
  byMethod: { id: number; name: string; amount: string; operations: number }[];
  receivable: { amount: string; operations: number };
}

export type CashLedgerKind = 'opening' | 'sale' | 'income' | 'expense' | 'withdrawal';

export interface CashLedgerEntry {
  id: string;
  /** ISO date-time. */
  at: string;
  kind: CashLedgerKind;
  /** Series and number of the receipt, e.g. `B001-4512`. */
  document: string | null;
  paymentMethod: string | null;
  description: string;
  /** Signed: what leaves the drawer is negative. */
  amount: string;
}

/** A shift and everything that happened in it, newest first. */
export interface CashSessionLedger {
  id: number;
  cashRegisterId: number;
  cashRegisterName: string;
  cashier: string | null;
  openedAt: string;
  closedAt: string | null;
  status: 'open' | 'closed';
  openingAmount: string;
  entries: CashLedgerEntry[];
}

/** What the X cut and the Z report of a shift print, as the server reads it. Amounts are decimal strings. */
export interface ShiftReportData {
  company: { name: string | null; ruc: string | null; branch: string | null };
  shift: {
    id: number;
    status: 'open' | 'closed';
    registerName: string;
    cashier: string | null;
    closedBy: string | null;
    openedAt: string | null;
    closedAt: string | null;
  };
  documents: {
    rows: {
      type: string;
      label: string;
      count: number;
      ranges: string[];
      taxable: string;
      igv: string;
      total: string;
    }[];
    count: number;
    taxable: string;
    igv: string;
    total: string;
  };
  sales: {
    total: string;
    byMethod: { name: string; operations: number; amount: string; percent: string }[];
    credit: { amount: string; operations: number; percent: string };
  };
  cash: {
    opening: string;
    cashSales: string;
    income: string;
    expenses: string;
    withdrawals: string;
    expected: string;
    counted: string | null;
    difference: string | null;
    /** Denomination in cents => quantity; null when the total was typed or the shift predates the record. */
    denominations: Record<string, number> | null;
    fundKept: boolean | null;
    withdrawnAtClose: string | null;
    note: string | null;
  };
  outflows: { at: string; kind: 'expense' | 'withdrawal'; description: string; amount: string }[];
}

export const cashSessionService = {
  /** The report of a shift as ESC/POS bytes for the till printer, like the sale ticket. */
  printReport: (sessionId: number | string) =>
    httpClient
      .get<ArrayBuffer>(API + `cash-sessions/${sessionId}/print-report`, {
        responseType: 'arraybuffer',
      })
      .then((response) => response.data),

  getReport: (sessionId: number | string) =>
    httpClient
      .get<ShiftReportData>(API + `cash-sessions/${sessionId}/report`, {
        errorPresentation: 'inline',
      })
      .then((response) => response.data),

  getLedger: (sessionId: number | string) =>
    httpClient
      .get<CashSessionLedger>(API + `cash-sessions/${sessionId}/ledger`, {
        errorPresentation: 'inline',
      })
      .then((response) => response.data),

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

  addMovement: (input: {
    sessionId: number;
    kind: CashMovementKind;
    amount: number;
    description: string;
  }) =>
    httpClient.post(
      API + 'cash-movements',
      {
        cashSession: API + `cash-sessions/${input.sessionId}`,
        kind: input.kind,
        amount: input.amount.toFixed(2),
        description: input.description,
      },
      { errorPresentation: 'inline' },
    ),
};
