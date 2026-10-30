import { ConsultInfoDto } from '@shared/api/consult.info.dto';
import { httpClient, type RequestConfig } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

class ConsultService {
  consultRuc(ruc: string, config?: RequestConfig) {
    return httpClient.get<ConsultInfoDto>(`${API}consults/ruc/${ruc}`, config);
  }

  consultDni(dni: string, config?: RequestConfig) {
    return httpClient.get<ConsultInfoDto>(`${API}consults/dni/${dni}`, config);
  }
}

export const consultService = new ConsultService();
