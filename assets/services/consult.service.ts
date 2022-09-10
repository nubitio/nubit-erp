import {map, Observable} from "rxjs";
import {RucInfoDto} from "../dto/ruc.info.dto";
import {DniInfoDto} from "../dto/dni.info.dto";
import axios from "axios";
import {fromPromise} from "rxjs/internal/observable/innerFrom";

export class ConsultService {

    consultRuc(ruc: string): Observable<RucInfoDto> {
        return fromPromise(axios.get<RucInfoDto>(`/api/ruc/${ruc}`))
            .pipe(map(response => response.data));
    }

    consultDni(dni: string): Observable<DniInfoDto> {
        return fromPromise(axios.get<DniInfoDto>(`/api/dni/${dni}`))
            .pipe(map(response => response.data));
    }
}