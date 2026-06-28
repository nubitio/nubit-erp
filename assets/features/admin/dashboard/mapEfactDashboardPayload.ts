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
  return VOUCHER_TYPE_LABELS[normalized] ?? VOUCHER_TYPE_LABELS[normalized.toUpperCase()] ?? normalized;
}

function abbreviateDayLabel(label: string): string {
  const key = label.trim().toLowerCase();
  return DAY_ABBREV[key] ?? label.slice(0, 3).toLowerCase();
}

/** Keep the chart readable — backend returns every day of the month. */
export function pickLast7SalesDays(salesByDay: SalesByDay[]): Array<{ label: string; total: number }> {
  const today = DateTime.now().setZone('America/Lima').day;

  return salesByDay
    .filter((row) => row.day <= today)
    .slice(-7)
    .map((row) => ({
      label: abbreviateDayLabel(row.label),
      total: row.total,
    }));
}

export type EfactDeclarativeDashboardPayload = {
  stats: {
    netSalesToday: number;
    invoicesToday: number;
    avgTicket: number;
    salesThisMonth: number;
    ytdRevenue: number;
    igvCollected: number;
    customersThisMonth: number;
    dailyGrowth: number | null;
    monthGrowth: number | null;
  };
  salesByDay: Array<{ label: string; total: number }>;
  salesByMonth: Array<{ label: string; total: number }>;
  paymentMethods: Array<{ method: string; total: number }>;
  voucherTypes: Array<{ name: string; total: number; count: number }>;
  recentSales: Array<{
    id: string;
    issuedAt: string;
    paymentMethod: string;
    total: number;
  }>;
  topProducts: Array<{ name: string; quantity: number; total: number }>;
  topCustomers: Array<{ name: string; invoices: number; total: number }>;
};

function truncateName(name: string, maxLength = 45): string {
  return name.length > maxLength ? name.slice(0, maxLength - 1) + '…' : name;
}

function resolveYtdRevenue(data: DashboardData): number {
  if (data.salesByMonth.length > 0) {
    return data.salesByMonth.reduce((sum, month) => sum + month.total, 0);
  }

  return data.summary.salesThisMonth;
}

export function mapEfactDashboardPayload(data: DashboardData): EfactDeclarativeDashboardPayload {
  return {
    stats: {
      netSalesToday: data.summary.netSalesToday,
      invoicesToday: data.summary.invoicesToday,
      avgTicket: data.summary.avgTicket,
      salesThisMonth: data.summary.salesThisMonth,
      ytdRevenue: resolveYtdRevenue(data),
      igvCollected: data.summary.salesThisMonth * (18 / 118),
      customersThisMonth: data.summary.customersThisMonth,
      dailyGrowth: data.summary.dailyGrowth,
      monthGrowth: data.summary.monthGrowth,
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
      total: row.total,
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