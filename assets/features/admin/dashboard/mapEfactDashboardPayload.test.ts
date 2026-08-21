import { DateTime } from 'luxon';
import { describe, expect, it } from 'vitest';

import { mapEfactDashboardPayload, pickLast7SalesDays } from './mapEfactDashboardPayload';
import type { DashboardData } from '../types';

const emptyDashboardData = (): DashboardData => ({
  summary: {
    salesToday: 0,
    salesYesterday: 0,
    salesWeek: 0,
    salesThisMonth: 1200,
    salesLastMonth: 0,
    invoicesToday: 3,
    invoicesThisMonth: 0,
    customersThisMonth: 8,
    customersWeek: 0,
    avgTicket: 45.5,
    avgTicketToday: 0,
    monthGrowth: 10,
    dailyGrowth: 5,
    refundsToday: 0,
    refundsThisMonth: 0,
    netSalesToday: 250,
    netSalesThisMonth: 0,
    receivablesTotal: 320,
    receivablesCount: 4,
    payablesTotal: 150,
    payablesCount: 2,
    pendingSunatCount: 1,
    lowStockCount: 3,
  },
  salesByDay: Array.from({ length: 23 }, (_, index) => ({
    day: index + 1,
    label: ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][index % 7],
    total: index === 22 ? 500 : 10,
  })),
  salesByMonth: [
    { label: 'Ene', total: 100 },
    { label: 'Feb', total: 200 },
  ],
  paymentMethods: [{ method: 'Efectivo', total: 300 }],
  paymentMethodTrend: { methods: [], points: [] },
  topProducts: [{ product: 'Arroz', quantity: 2, total: 20 }],
  purchasesSummary: {
    purchasesToday: 0,
    purchasesWeek: 0,
    purchasesThisMonth: 0,
    purchasesLastMonth: 0,
    receiptsToday: 0,
    receiptsThisMonth: 0,
    monthGrowth: null,
  },
  purchasesByDay: [],
  topPurchasedProducts: [],
  topCustomers: [{ customer: 'ACME SAC', invoices: 4, total: 900 }],
  voucherTypes: [{ type: '03', count: 4, total: 400 }],
  lowStockProducts: [],
  paymentMethodsToday: [],
  hourlySalesToday: [],
  recentSalesToday: [
    { id: '99', issuedAt: '10:30', paymentMethod: 'Efectivo', total: 50 },
  ],
});

describe('pickLast7SalesDays', () => {
  it('returns exactly 7 days, filling missing days with zero total', () => {
    const rows = pickLast7SalesDays(emptyDashboardData().salesByDay);

    expect(rows).toHaveLength(7);
    expect(rows.at(-1)?.label).toMatch(/^(lun|mar|mié|jue|vie|sáb|dom)$/);

    // — if today is early in the month, some leading days fall outside
    //   the current month's data and should be zero-filled
    const zeroDays = rows.filter((r) => r.total === 0);
    const today = DateTime.now().setZone('America/Lima').day;
    if (today < 7) {
      expect(zeroDays.length).toBeGreaterThan(0);
    }
  });

  it('zero-fills a day with no backend data', () => {
    const today = DateTime.now().setZone('America/Lima').day;

    // only provide data for today, nothing else
    const rows = pickLast7SalesDays([
      { day: today, label: 'Lunes', total: 999 },
    ]);

    expect(rows).toHaveLength(7);
    // today's bar has data; the other 6 are zero
    expect(rows.find((r) => r.total === 999)).toBeDefined();
    expect(rows.filter((r) => r.total === 0)).toHaveLength(6);
  });
});

describe('mapEfactDashboardPayload', () => {
  it('maps efact overview data into declarative dashboard paths', () => {
    const payload = mapEfactDashboardPayload(emptyDashboardData());

    expect(payload.stats.netSalesToday).toBe(250);
    expect(payload.stats.invoicesToday).toBe(3);
    expect(payload.salesByDay.length).toBe(7);
    expect(payload.voucherTypes[0]?.name).toBe('Boleta');
    expect(payload.recentSales[0]?.paymentMethod).toBe('Efectivo');
    expect(payload.topProducts[0]?.name).toBe('Arroz');
    expect(payload.stats.salesLast12Months).toBe(300);
    expect(payload.stats.ytdRevenue).toBe(300);
    expect(payload.stats.igvCollected).toBeCloseTo(1200 * (18 / 118), 2);
    expect(payload.stats.customersThisMonth).toBe(8);
    expect(payload.salesByMonth).toHaveLength(2);
    expect(payload.paymentMethods[0]?.method).toBe('Efectivo');
    expect(payload.topCustomers[0]?.name).toBe('ACME SAC');
    expect(payload.stats.receivablesTotal).toBe(320);
    expect(payload.stats.payablesTotal).toBe(150);
    expect(payload.stats.pendingSunatCount).toBe(1);
  });

  it('hides the daily trend when there are no sales yet today', () => {
    const data = emptyDashboardData();
    data.summary.netSalesToday = 0;
    data.summary.dailyGrowth = -100;

    expect(mapEfactDashboardPayload(data).stats.dailyGrowth).toBeNull();
  });
});
