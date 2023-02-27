import {ConsultInfoDto} from "../dto/consult.info.dto";
import {httpClient} from "../core/http/HttpClient";

export class ConsultService {

    consultRuc(ruc: string) {
        return httpClient.get<ConsultInfoDto>(`/api/consults/ruc/${ruc}`);
    }

    consultDni(dni: string) {
        return httpClient.get<ConsultInfoDto>(`/api/consults/dni/${dni}`);
    }
}