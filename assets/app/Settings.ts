import { settingsService } from '../features/settings';
import { SettingDto } from '@shared/api/setting.dto';
import { lastValueFrom } from 'rxjs';

export const Setting = {
  AUTO_INVOICE: 'app_option_auto_invoice',
  CHECK_STOCK: 'app_option_check_stock',
  DEFAULT_CURRENCY: 'sales_default_currency',
  DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',
  PRINT_METHOD: 'print_method',
} as const;

export type PrintMethod = 'WEBUSB' | 'LOCAL_AGENT';

const STORAGE_KEY = 'settings';
const STORAGE_TS_KEY = 'settings_updated_at';
const CACHE_TTL_MS = 5 * 60 * 1000; // 5 minutes

class Settings {
  private settings: SettingDto[] = [];
  /** Guards against concurrent init() calls. */
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
    // Deduplicate concurrent refreshes (e.g. multiple get() calls in parallel).
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
      const response = await lastValueFrom(settingsService.getAll());
      this.settings = response.data;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.settings));
      localStorage.setItem(STORAGE_TS_KEY, new Date().toISOString());
    } catch (error) {
      // Keep cached settings when refresh fails, but log for observability.
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
    return 'WEBUSB'; // default
  }

  getPrintMethodCached(): PrintMethod {
    const setting = this.getCached(Setting.PRINT_METHOD);
    const value = setting?.value;
    if (value === 'LOCAL_AGENT' || value === 'WEBUSB') return value;
    return 'WEBUSB'; // default
  }
}

export const settings = new Settings();
