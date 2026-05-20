import { ConsultInfoDto } from '@shared/api/consult.info.dto';
import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';

class ConsultService {
  consultRuc(ruc: string) {
    return httpClient.get<ConsultInfoDto>(`${API_BASE_URL}consults/ruc/${ruc}`);
  }

  consultDni(dni: string) {
    return httpClient.get<ConsultInfoDto>(`${API_BASE_URL}consults/dni/${dni}`);
  }
}

export const consultService = new ConsultService();
