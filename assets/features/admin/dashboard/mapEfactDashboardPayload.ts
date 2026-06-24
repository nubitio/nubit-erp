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
  };
  salesByDay: Array<{ label: string; total: number }>;
  voucherTypes: Array<{ name: string; total: number; count: number }>;
  recentSales: Array<{
    id: string;
    issuedAt: string;
    paymentMethod: string;
    total: number;
  }>;
  topProducts: Array<{ name: string; quantity: number; total: number }>;
};

export function mapEfactDashboardPayload(data: DashboardData): EfactDeclarativeDashboardPayload {
  return {
    stats: {
      netSalesToday: data.summary.netSalesToday,
      invoicesToday: data.summary.invoicesToday,
      avgTicket: data.summary.avgTicket,
      salesThisMonth: data.summary.salesThisMonth,
    },
    salesByDay: pickLast7SalesDays(data.salesByDay),
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
      name: row.product,
      quantity: row.quantity,
      total: row.total,
    })),
  };
}