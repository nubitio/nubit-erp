import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { getCoreApiBaseUrl } from '@nubitio/core';

const BASE = getCoreApiBaseUrl();

export interface GastoPorCategoria {
  categoria: string;
  monto: number;
}

export interface ProfitAndLossResponse {
  period: string;
  periodLabel: string;
  ingresos: {
    bruto: number;
    notasCredito: number;
    neto: number;
  };
  costoVentas: {
    monto: number;
    isEstimate: boolean;
  };
  utilidadBruta: number;
  gastosOperativos: {
    total: number;
    porCategoria: GastoPorCategoria[];
  };
  utilidadNeta: number;
  historial: Array<{
    periodo: string;
    label: string;
    ingresos: number;
    costoVentas: number;
    utilidadNeta: number;
  }>;
}

export interface CashFlowWeek {
  weekKey: string;
  entradas: number;
  salidas: number;
  neto: number;
}

export interface CashFlowProjectedWeek {
  weekKey: string;
  cobrosEsperados: number;
  pagosEsperados: number;
  netoEsperado: number;
}

export interface CashFlowResponse {
  from: string;
  to: string;
  real: CashFlowWeek[];
  proyectado: CashFlowProjectedWeek[];
}

export const financialReportsApi = {
  profitAndLoss(period?: string) {
    const params = period ? `?period=${period}` : '';
    return httpRequestData(
      httpClient.get<ProfitAndLossResponse>(`${BASE}financial-reports/profit-and-loss${params}`),
    );
  },
  cashFlow(from?: string, to?: string) {
    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    const query = params.toString();
    return httpRequestData(
      httpClient.get<CashFlowResponse>(
        `${BASE}financial-reports/cash-flow${query ? `?${query}` : ''}`,
      ),
    );
  },
  exportUrl(period?: string, from?: string, to?: string) {
    const params = new URLSearchParams();
    if (period) params.set('period', period);
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    const query = params.toString();
    return `${BASE}financial-reports/export.xlsx${query ? `?${query}` : ''}`;
  },
};
