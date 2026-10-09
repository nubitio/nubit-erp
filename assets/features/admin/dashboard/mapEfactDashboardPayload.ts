import { DateTime } from 'luxon';

import type { DashboardData, SalesByDay } from '../types';

const VOUCHER_TYPE_LABELS: Record<string, string> = {
  '01': 'Factura',
  '03': 'Boleta',
  '07': 'Nota de crédito',
  '08': 'Nota de débito',
  FACTURA: 'Factura',
  BOLETA: 'Boleta',
  'NOTA DE CREDITO': 'Nota de crédito',
  'NOTA DE DEBITO': 'Nota de débito',
};

const DAY_ABBREV: Record<string, string> = {
  lunes: 'lun',
  martes: 'mar',
  miércoles: 'mié',
  miercoles: 'mié',
  jueves: 'jue',
  viernes: 'vie',
  sábado: 'sáb',
  sabado: 'sáb',
  domingo: 'dom',
  monday: 'lun',
  tuesday: 'mar',
  wednesday: 'mié',
  thursday: 'jue',
  friday: 'vie',
  saturday: 'sáb',
  sunday: 'dom',
};

function voucherTypeLabel(type: string): string {
  const normalized = type.trim();
  return (
    VOUCHER_TYPE_LABELS[normalized] ?? VOUCHER_TYPE_LABELS[normalized.toUpperCase()] ?? normalized
  );
}

function abbreviateDayLabel(label: string): string {
  const key = label.trim().toLowerCase();
  return DAY_ABBREV[key] ?? label.slice(0, 3).toLowerCase();
}

/**
 * Last 7 calendar days, oldest → newest.
 *
 * `salesByDay` is the current month's per-day totals, keyed by day-of-month — so
 * a day is only matched when it actually falls in the current month, otherwise
 * it is zero-filled (e.g. early in the month the leading days have no data).
 * Labels come from the real date, keeping the 7 weekday names distinct (a
 * previous version matched by day number across months and could emit duplicate
 * labels, which broke the chart's category axis).
 */
export function pickLast7SalesDays(
  salesByDay: SalesByDay[],
): Array<{ label: string; total: number }> {
  const today = DateTime.now().setZone('America/Lima');

  return Array.from({ length: 7 }, (_, i) => {
    const dt = today.minus({ days: 6 - i });
    const found = dt.hasSame(today, 'month')
      ? salesByDay.find((row) => row.day === dt.day)
      : undefined;
    return {
      label: abbreviateDayLabel(dt.setLocale('es').weekdayLong ?? dt.toFormat('ccc')),
      total: found?.total ?? 0,
    };
  });
}

export type EfactDeclarativeDashboardPayload = {
  stats: {
    netSalesToday: number;
    invoicesToday: number;
    avgTicket: number;
    salesThisMonth: number;
    salesLast12Months: number;
    ytdRevenue: number;
    igvCollected: number;
    customersThisMonth: number;
    dailyGrowth: number | null;
    monthGrowth: number | null;
    receivablesTotal: number;
    payablesTotal: number;
    pendingSunatCount: number;
    lowStockCount: number;
    receivablesCount: number;
    payablesCount: number;
  };
  salesByDay: Array<{ label: string; total: number }>;
  salesByMonth: Array<{ label: string; total: number }>;
  paymentMethods: Array<{ method: string; total: number }>;
  voucherTypes: Array<{ name: string; total: number; count: number }>;
  recentSales: Array<{
    id: string;
    issuedAt: string;
    paymentMethod: string;
    document: string;
    total: number;
  }>;
  topProducts: Array<{ name: string; quantity: number; total: number }>;
  topCustomers: Array<{ name: string; invoices: number; total: number }>;
  lowStock: Array<{ name: string; currentStock: number; minStock: number }>;
};

function truncateName(name: string, maxLength = 45): string {
  return name.length > maxLength ? name.slice(0, maxLength - 1) + '…' : name;
}

function saneGrowth(value: number | null): number | null {
  if (value == null || !Number.isFinite(value) || Math.abs(value) > 200) return null;
  return value;
}

function resolveSalesLast12Months(data: DashboardData): number {
  if (data.salesByMonth.length > 0) {
    return data.salesByMonth.reduce((sum, month) => sum + month.total, 0);
  }

  return data.summary.salesThisMonth;
}

export function mapEfactDashboardPayload(data: DashboardData): EfactDeclarativeDashboardPayload {
  const salesLast12Months = resolveSalesLast12Months(data);

  return {
    stats: {
      netSalesToday: data.summary.netSalesToday,
      invoicesToday: data.summary.invoicesToday,
      avgTicket: data.summary.avgTicket,
      salesThisMonth: data.summary.salesThisMonth,
      salesLast12Months,
      ytdRevenue: salesLast12Months,
      igvCollected: data.summary.salesThisMonth * (18 / 118),
      customersThisMonth: data.summary.customersThisMonth,
      dailyGrowth: data.summary.netSalesToday <= 0 ? null : saneGrowth(data.summary.dailyGrowth),
      monthGrowth: data.summary.monthGrowth === 0 ? null : saneGrowth(data.summary.monthGrowth),
      receivablesTotal: data.summary.receivablesTotal,
      payablesTotal: data.summary.payablesTotal,
      pendingSunatCount: data.summary.pendingSunatCount,
      lowStockCount: data.summary.lowStockCount,
      receivablesCount: data.summary.receivablesCount,
      payablesCount: data.summary.payablesCount,
    },
    salesByDay: pickLast7SalesDays(data.salesByDay),
    salesByMonth: data.salesByMonth.map((row) => ({
      label: row.label,
      total: row.total,
    })),
    paymentMethods: data.paymentMethods.map((row) => ({
      method: row.method,
      total: row.total,
    })),
    voucherTypes: data.voucherTypes.map((row) => ({
      name: voucherTypeLabel(row.type),
      total: row.total,
      count: row.count,
    })),
    recentSales: data.recentSalesToday.map((row) => ({
      id: row.id,
      issuedAt: row.issuedAt,
      paymentMethod: row.paymentMethod,
      document: [row.series, row.number].filter(Boolean).join('-') || '—',
      total: row.total,
    })),
    lowStock: data.lowStockProducts.map((row) => ({
      name: truncateName(row.product),
      currentStock: row.currentStock,
      minStock: row.minStock,
    })),
    topProducts: data.topProducts.map((row) => ({
      name: truncateName(row.product),
      quantity: row.quantity,
      total: row.total,
    })),
    topCustomers: data.topCustomers.map((row) => ({
      name: row.customer,
      invoices: row.invoices,
      total: row.total,
    })),
  };
}
