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
  },
  salesByDay: Array.from({ length: 23 }, (_, index) => ({
    day: index + 1,
    label: ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][index % 7],
    total: index === 22 ? 500 : 10,
  })),
  salesByMonth: [],
  paymentMethods: [],
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
  topCustomers: [],
  voucherTypes: [{ type: '03', count: 4, total: 400 }],
  lowStockProducts: [],
  paymentMethodsToday: [],
  hourlySalesToday: [],
  recentSalesToday: [
    { id: '99', issuedAt: '10:30', paymentMethod: 'Efectivo', total: 50 },
  ],
});

describe('pickLast7SalesDays', () => {
  it('returns at most seven day labels for the current month', () => {
    const today = DateTime.now().setZone('America/Lima').day;
    const rows = pickLast7SalesDays(emptyDashboardData().salesByDay);

    expect(rows.length).toBeLessThanOrEqual(7);
    expect(rows.length).toBe(Math.min(7, today));
    expect(rows.at(-1)?.label).toMatch(/^(lun|mar|mié|jue|vie|sáb|dom)$/);
  });
});

describe('mapEfactDashboardPayload', () => {
  it('maps efact overview data into declarative dashboard paths', () => {
    const payload = mapEfactDashboardPayload(emptyDashboardData());

    expect(payload.stats.netSalesToday).toBe(250);
    expect(payload.stats.invoicesToday).toBe(3);
    expect(payload.salesByDay.length).toBeLessThanOrEqual(7);
    expect(payload.voucherTypes[0]?.name).toBe('Boleta');
    expect(payload.recentSales[0]?.paymentMethod).toBe('Efectivo');
    expect(payload.topProducts[0]?.name).toBe('Arroz');
  });
});