import {Observable} from "rxjs";
import {RucInfoDto} from "../dto/ruc.info.dto";
import {DniInfoDto} from "../dto/dni.info.dto";
import {httpClient} from "../utils/HttpClient";

export class ConsultService {

    consultRuc(ruc: string): Observable<RucInfoDto> {
        return httpClient.get<RucInfoDto>(`/api/ruc/${ruc}`);
    }

    consultDni(dni: string): Observable<DniInfoDto> {
        return httpClient.get<DniInfoDto>(`/api/dni/${dni}`);
    }
}