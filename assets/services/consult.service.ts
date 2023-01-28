import {Observable} from "rxjs";
import {ConsultInfoDto} from "../dto/consult.info.dto";
import {httpClient} from "../core/utils/HttpClient";

export class ConsultService {

    consultRuc(ruc: string): Observable<ConsultInfoDto> {
        return httpClient.get<ConsultInfoDto>(`/api/consults/ruc/${ruc}`);
    }

    consultDni(dni: string): Observable<ConsultInfoDto> {
        return httpClient.get<ConsultInfoDto>(`/api/consults/dni/${dni}`);
    }
}