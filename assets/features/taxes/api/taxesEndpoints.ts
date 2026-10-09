import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { getCoreApiBaseUrl } from '@nubitio/core';

const BASE = getCoreApiBaseUrl();

export interface IgvSummaryResponse {
  period: string;
  periodLabel: string;
  vencimiento: string;
  kpi: {
    igvPorPagar: number;
    creditoFiscal: number;
    detraccionesPendientes: number;
  };
  igvMensual: {
    debitoFiscal: { baseImponible: number; igv: number };
    creditoFiscal: { baseImponible: number; igv: number };
    retencionesSufridas: number;
    igvPorPagar: number;
  };
  historial: Array<{
    periodo: string;
    label: string;
    igvVentas: number;
    igvCompras: number;
    igvPagar: number;
    estado: 'abierto' | 'declarado';
  }>;
}

export interface DetraccionResponse {
  cuenta: string;
  depositado: number;
  pendiente: number;
  items: Array<{
    series: string;
    number: string;
    cliente: string;
    concepto: string;
    spot_percent: number;
    base: number;
    detraccion: number;
    estado: 'pendiente' | 'depositado';
  }>;
}

export interface RetencionPercepcionResponse {
  items: Array<{
    type: string;
    direction: string;
    counterparty_name: string;
    document_reference: string;
    rate: number;
    base_amount: number;
    amount: number;
  }>;
}

export const taxesApi = {
  igvSummary(period?: string) {
    const params = period ? `?period=${period}` : '';
    return httpRequestData(httpClient.get<IgvSummaryResponse>(`${BASE}taxes/igv-summary${params}`));
  },
  detracciones(period?: string) {
    const params = period ? `?period=${period}` : '';
    return httpRequestData(
      httpClient.get<DetraccionResponse>(`${BASE}taxes/detracciones${params}`),
    );
  },
  retencionesPercepciones(period?: string) {
    const params = period ? `?period=${period}` : '';
    return httpRequestData(
      httpClient.get<RetencionPercepcionResponse>(`${BASE}taxes/retenciones-percepciones${params}`),
    );
  },
};
