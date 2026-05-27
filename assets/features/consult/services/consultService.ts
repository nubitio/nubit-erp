import { ConsultInfoDto } from '@shared/api/consult.info.dto';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

class ConsultService {
  consultRuc(ruc: string) {
    return httpClient.get<ConsultInfoDto>(`${API}consults/ruc/${ruc}`);
  }

  consultDni(dni: string) {
    return httpClient.get<ConsultInfoDto>(`${API}consults/dni/${dni}`);
  }
}

export const consultService = new ConsultService();
