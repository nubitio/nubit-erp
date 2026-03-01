import { settingsService } from './features/settings';
import { SettingDto } from './dto/setting.dto';
import { lastValueFrom } from 'rxjs';

export const Setting = {
  AUTO_INVOICE: 'app_option_auto_invoice',
  CHECK_STOCK: 'app_option_check_stock',
  DEFAULT_CURRENCY: 'sales_default_currency',
  DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',
} as const;

class Settings {
  private settings: SettingDto[] = [];

  constructor() {
    this.settings = JSON.parse(localStorage.getItem('settings') || '[]');
  }

  async init() {
    try {
      const response = await lastValueFrom(settingsService.getAll());
      localStorage.setItem('settings', JSON.stringify(response.data));
      localStorage.setItem('settings_updated_at', new Date().toISOString());
      const settings = localStorage.getItem('settings');
      if (settings) {
        this.settings = JSON.parse(settings);
      }
    } catch (error) {
      console.error('Error initializing settings:', error);
    }
  }

  async get(key: string): Promise<SettingDto | undefined> {
    const settingsUpdatedAt = localStorage.getItem('settings_updated_at');
    if (
      !settingsUpdatedAt ||
      new Date(settingsUpdatedAt).getTime() < new Date().getTime() - 5 * 60 * 1000
    ) {
      await this.init();
    }
    return this.settings.find((s) => s.name === key);
  }

  getCached(key: string): SettingDto | undefined {
    return this.settings.find((s) => s.name === key);
  }
}

export const settings = new Settings();
