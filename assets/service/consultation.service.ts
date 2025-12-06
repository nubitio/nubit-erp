import { httpClient } from '../core/http/HttpClient';
import FilterRule from '../core/common/FilterRule';
import { ConsultationDto } from '../dto/consultation.dto';
import { Constants } from '../core/common/Constants';

class ConsultationService {
  getConsultationsByPatientId(patientId: number) {
    let patientFilter: FilterRule = {
      field: 'patient',
      op: 'equal',
      value: patientId,
    };
    return httpClient.get<ConsultationDto[]>(
      `${Constants.API_BASE_URL}consultations?filterRules=[${JSON.stringify(patientFilter)}]`,
    );
  }

  getOneById(id: number) {
    return httpClient.get<any>(Constants.API_BASE_URL + 'consultations/' + id);
  }

  getPatientSales(id: number) {
    return httpClient.get<any>(Constants.API_BASE_URL + 'customers/' + id + '/sales');
  }
}

export const consultationService = new ConsultationService();
