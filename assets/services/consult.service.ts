import {Observable} from "rxjs";
import {ConsultInfoDto} from "../dto/consult.info.dto";
import {httpClient} from "../core/http/HttpClient";
import ApiResponse from "../core/http/ApiResponse";

export class ConsultService {

    consultRuc(ruc: string): Observable<ApiResponse<ConsultInfoDto>> {
        return httpClient.get<ConsultInfoDto>(`/api/consults/ruc/${ruc}`);
    }

    consultDni(dni: string): Observable<ApiResponse<ConsultInfoDto>> {
        return httpClient.get<ConsultInfoDto>(`/api/consults/dni/${dni}`);
    }
}