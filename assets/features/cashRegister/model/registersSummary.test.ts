import { describe, expect, it } from 'vitest';
import type { CashRegisterDashboardDto } from '../services/cashboxService';
import { summarizeRegisters } from './registersSummary';

const NOW = new Date('2026-11-23T12:00:00Z').getTime();

const register = (patch: Partial<CashRegisterDashboardDto>): CashRegisterDashboardDto => ({
  id: 1,
  name: 'Caja 01',
  cashSessionId: null,
  cashShiftId: null,
  openedAt: null,
  closedAt: null,
  cashier: null,
  balance: 0,
  status: 'closed',
  todaySales: '0.00',
  ...patch,
});

describe('summarizeRegisters', () => {
  it('counts only open registers and totals their cash and sales', () => {
    const summary = summarizeRegisters(
      [
        register({ id: 1, status: 'open', balance: 100.5, todaySales: '40.00' }),
        register({ id: 2, status: 'open', balance: 50, todaySales: '10.25' }),
        register({ id: 3, status: 'closed', balance: 999, todaySales: '999.00' }),
      ],
      NOW,
    );

    expect(summary).toMatchObject({ total: 3, open: 2, expectedCash: 150.5, sales: 50.25 });
  });

  it('flags a shift open for more than 24 hours', () => {
    const summary = summarizeRegisters(
      [
        register({ id: 1, status: 'open', openedAt: '2026-11-22T11:00:00Z' }),
        register({ id: 2, status: 'open', openedAt: '2026-11-23T08:00:00Z' }),
        register({ id: 3, status: 'closed', openedAt: '2026-11-01T08:00:00Z' }),
      ],
      NOW,
    );

    expect(summary.stale.map((r) => r.id)).toEqual([1]);
  });
});
