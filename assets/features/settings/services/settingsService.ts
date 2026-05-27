import { httpClient } from '@shared/api/HttpClient';
import { SettingDto } from '@shared/api/setting.dto';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

class SettingsService {
  getAll() {
    return httpClient.get<SettingDto[]>(`${API}configs`);
  }
}

export const settingsService = new SettingsService();
