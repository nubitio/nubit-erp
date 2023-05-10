import {httpClient} from "../core/http/HttpClient";
import {PatientDto} from "../dto/patient.dto";

class PatientService {
    getPatientById(patientId: number) {
        return httpClient.get<PatientDto>(`/api/patients/${patientId}`);
    }
}

export const patientService = new PatientService();