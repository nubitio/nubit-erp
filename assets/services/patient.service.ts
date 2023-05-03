import {httpClient} from "../core/http/HttpClient";

class PatientService {
    getPatientById(patientId: number) {
        return httpClient.get<any>(`/api/patients/${patientId}`);
    }
}

export const patientService = new PatientService();