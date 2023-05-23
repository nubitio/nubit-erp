import {httpClient} from "../core/http/HttpClient";
import {SettingDto} from "../dto/setting.dto";

class SettingsService {
    getAll() {
        return httpClient.get<SettingDto[]>('/api/settings/');
    }
}

export const settingsService = new SettingsService();
