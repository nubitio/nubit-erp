import { httpClient } from '@shared/api/HttpClient';

// ── Types ─────────────────────────────────────────────────────────────────────
// These are the canonical types for the new /api/tenant/settings endpoint.
// Run `yarn generate:api-types` to regenerate from the OpenAPI spec whenever
// the TenantSettings entity changes in PHP.

export interface TenantSettingsDto {
  taxRegime: 'GENERAL' | 'NRUS';
  igv: number;
  rc: number;
  printLogo: boolean;
  requireCashRegister: boolean;
  printMethod: 'WEBUSB' | 'LOCAL_AGENT';
  autoInvoice: boolean;
  autoPrint: boolean;
  cashRoundingEnabled: boolean;
  defaultCurrency: 'PEN' | 'USD';
  negativeStockPolicy: 'STRICT' | 'WARNING_ONLY' | 'ALLOW_NEGATIVE';
}

export interface SunatStateDto {
  sendEnabled: boolean;
  receiptRequired: boolean;
  rejectionStreak: number;
  rejectionThreshold: number;
}

export interface ExchangeRateDto {
  currency: string;
  rate: string;
  source: 'BCRP_AUTO' | 'MANUAL';
  updatedAt: string;
}

// ── TenantSettingsService ─────────────────────────────────────────────────────

class TenantSettingsService {
  private readonly BASE = '/api/tenant/settings';
  private readonly SUNAT_BASE = '/api/tenant/sunat-state';

  getSettings(): Promise<TenantSettingsDto> {
    return httpClient.get<TenantSettingsDto>(this.BASE).then((res) => res.data);
  }

  patchSettings(patch: Partial<TenantSettingsDto>): Promise<TenantSettingsDto> {
    return httpClient.patch<TenantSettingsDto>(this.BASE, patch).then((res) => res.data);
  }

  getSunatState(): Promise<SunatStateDto> {
    return httpClient.get<SunatStateDto>(this.SUNAT_BASE).then((res) => res.data);
  }

  patchSunatState(patch: Partial<SunatStateDto>): Promise<SunatStateDto> {
    return httpClient.patch<SunatStateDto>(this.SUNAT_BASE, patch).then((res) => res.data);
  }
}

export const tenantSettingsService = new TenantSettingsService();
