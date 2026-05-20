import { httpClient } from '@shared/api/HttpClient';
import { SettingDto } from '@shared/api/setting.dto';
import { API_BASE_URL } from '@app/constants';

class SettingsService {
  getAll() {
    return httpClient.get<SettingDto[]>(`${API_BASE_URL}configs`);
  }
}

export const settingsService = new SettingsService();
