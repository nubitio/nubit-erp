import {httpClient} from "../core/http/HttpClient";
import {Observable} from "rxjs";

export class SaleService {

    voidSale(saleId: string, reason: string): Observable<any> {
        return httpClient.post(`/sale/void/${saleId}`, {reason});
    }

    makeInvoice(saleId: string): Observable<any> {
        return httpClient.post(`/sale/invoice/${saleId}`);
    }
}