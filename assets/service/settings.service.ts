import {httpClient} from "../core/http/HttpClient";
import {SettingDto} from "../dto/setting.dto";
import {Constants} from "../core/common/Constants";

class SettingsService {
    getAll() {
        return httpClient.get<SettingDto[]>(`${Constants.API_BASE_URL}settings`);
    }
}

export const settingsService = new SettingsService();
