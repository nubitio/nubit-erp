import {httpClient} from "../core/http/HttpClient";
import FilterRule from "../core/common/FilterRule";

class ConsultationService {
    getConsultationsByPatientId(patientId: number) {
        let patientFilter: FilterRule = {
            field: 'patient',
            op: 'equal',
            value: patientId
        }
        return httpClient.get<any>(`/api/consultations?filterRules=[${JSON.stringify(patientFilter)}]`);
    }
}

export const consultationService = new ConsultationService();