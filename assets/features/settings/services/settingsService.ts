import { httpClient } from '@shared/api/HttpClient';
import { SettingDto } from '../../../dto/setting.dto';
import { Constants } from '@platform/common/Constants';

class SettingsService {
  getAll() {
    return httpClient.get<SettingDto[]>(`${Constants.API_BASE_URL}configs`);
  }
}

export const settingsService = new SettingsService();
