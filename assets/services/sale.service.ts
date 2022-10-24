import {httpClient} from "../utils/HttpClient";
import {Observable} from "rxjs";

export class SaleService {

    voidSale(saleId: string, reason: string): Observable<any> {
        return httpClient.post(`/sale/void/${saleId}`, {reason});
    }
}