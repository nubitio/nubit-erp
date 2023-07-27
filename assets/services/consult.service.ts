import {ConsultInfoDto} from "../dto/consult.info.dto";
import {httpClient} from "../core/http/HttpClient";
import {Constants} from "../core/common/Constants";

export class ConsultService {

    consultRuc(ruc: string) {
        return httpClient.get<ConsultInfoDto>(`${Constants.API_BASE_URL}consults/ruc/${ruc}`);
    }

    consultDni(dni: string) {
        return httpClient.get<ConsultInfoDto>(`${Constants.API_BASE_URL}consults/dni/${dni}`);
    }
}

export const consultService = new ConsultService();
