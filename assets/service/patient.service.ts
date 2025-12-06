import { httpClient } from '../core/http/HttpClient';
import { PatientDto } from '../dto/patient.dto';
import { Constants } from '../core/common/Constants';

class PatientService {
  getPatientById(patientId: number) {
    return httpClient.get<PatientDto>(`${Constants.API_BASE_URL}patients/${patientId}`);
  }
}

export const patientService = new PatientService();
