import type { CashRegisterDashboardDto } from '../services/cashboxService';

const STALE_AFTER_MS = 24 * 60 * 60 * 1000;

export interface RegistersSummary {
  total: number;
  open: number;
  /** Cash the system expects across the open registers. */
  expectedCash: number;
  /** Sales rung up in the shifts that are still open. */
  sales: number;
  /** Registers whose open shift started more than 24 hours ago. */
  stale: CashRegisterDashboardDto[];
}

export function summarizeRegisters(
  registers: CashRegisterDashboardDto[],
  now: number = Date.now(),
): RegistersSummary {
  const open = registers.filter((register) => register.status === 'open');

  return {
    total: registers.length,
    open: open.length,
    expectedCash: open.reduce((sum, register) => sum + register.balance, 0),
    sales: open.reduce((sum, register) => sum + Number(register.todaySales), 0),
    stale: open.filter(
      (register) =>
        register.openedAt !== null && now - new Date(register.openedAt).getTime() > STALE_AFTER_MS,
    ),
  };
}
