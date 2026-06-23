import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { SettingDto } from '@shared/api/setting.dto';

const API = getCoreApiBaseUrl();

export const Setting = {
  AUTO_INVOICE: 'app_option_auto_invoice',
  INVENTORY_NEGATIVE_STOCK_POLICY: 'inventory_negative_stock_policy',
  DEFAULT_CURRENCY: 'sales_default_currency',
  DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',
  PRINT_METHOD: 'print_method',
} as const;

export type PrintMethod = 'WEBUSB' | 'LOCAL_AGENT';

const STORAGE_KEY = 'settings';
const STORAGE_TS_KEY = 'settings_updated_at';
const CACHE_TTL_MS = 5 * 60 * 1000;

class Settings {
  private settings: SettingDto[] = [];
  private refreshPromise: Promise<void> | null = null;

  constructor() {
    try {
      this.settings = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch {
      console.warn('[Settings] Failed to parse cached settings from localStorage.');
      this.settings = [];
    }
  }

  async init(): Promise<void> {
    if (this.refreshPromise) return this.refreshPromise;

    this.refreshPromise = this.doRefresh();

    try {
      await this.refreshPromise;
    } finally {
      this.refreshPromise = null;
    }
  }

  private async doRefresh(): Promise<void> {
    try {
      const response = await httpClient.get<SettingDto[]>(`${API}configs`);
      this.settings = response.data;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.settings));
      localStorage.setItem(STORAGE_TS_KEY, new Date().toISOString());
    } catch (error) {
      console.warn('[Settings] Failed to refresh settings from server; using cached values.', error);
    }
  }

  private isCacheStale(): boolean {
    const updatedAt = localStorage.getItem(STORAGE_TS_KEY);
    if (!updatedAt) return true;
    return Date.now() - new Date(updatedAt).getTime() > CACHE_TTL_MS;
  }

  async get(key: string): Promise<SettingDto | undefined> {
    if (this.isCacheStale()) {
      await this.init();
    }
    return this.settings.find((s) => s.name === key);
  }

  getCached(key: string): SettingDto | undefined {
    return this.settings.find((s) => s.name === key);
  }

  async getPrintMethod(): Promise<PrintMethod> {
    const setting = await this.get(Setting.PRINT_METHOD);
    const value = setting?.value;
    if (value === 'LOCAL_AGENT' || value === 'WEBUSB') return value;
    return 'WEBUSB';
  }

  getPrintMethodCached(): PrintMethod {
    const setting = this.getCached(Setting.PRINT_METHOD);
    const value = setting?.value;
    if (value === 'LOCAL_AGENT' || value === 'WEBUSB') return value;
    return 'WEBUSB';
  }
}

export const settings = new Settings();
