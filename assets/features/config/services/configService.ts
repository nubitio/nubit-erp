import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import type { ConfigDto } from '../model/ConfigKey';

const endpoints = createEndpoints('configs');

type ConfigCollectionResponse = {
  'hydra:member'?: ConfigDto[];
};

class ConfigService {
  getAll(): Promise<ConfigDto[]> {
    return httpClient.get<ConfigCollectionResponse | ConfigDto[]>(endpoints.list()).then((res) => {
      // API Platform returns hydra:member for collections
      const data = res.data;
      return Array.isArray(data) ? data : (data['hydra:member'] ?? []);
    });
  }

  patch(id: number, value: string): Promise<ConfigDto> {
    return httpClient.patch<ConfigDto>(endpoints.update(id), { value }).then((res) => res.data);
  }
}

export const configService = new ConfigService();
