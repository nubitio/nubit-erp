import {settingsService} from "./services/settings.service";
import {SettingDto} from "./dto/setting.dto";
import {lastValueFrom} from "rxjs";

export const Setting = {
    AUTO_INVOICE: 'app_option_invoicing_automatically',
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
            const settings = localStorage.getItem('settings');
            if (settings) {
                this.settings = JSON.parse(settings);
            }
        } catch (error) {
            console.error('Error initializing settings:', error);
        }
    }

    async get(key: string): Promise<SettingDto | undefined> {
        if (this.settings.length === 0) {
            await this.init();
        }
        return this.settings.find(s => s.name === key);
    }
}

export const settings = new Settings();
