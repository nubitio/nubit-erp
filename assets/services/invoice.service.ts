import {httpClient} from "../core/http/HttpClient";
import {Observable} from "rxjs";

export class InvoiceService {

    consultInvoice(saleId: string): Observable<any> {
        return httpClient.get(`/invoice/${saleId}/consult`);
    }

    sendEmail(saleId: string): Observable<any> {
        return httpClient.post(`/invoice/${saleId}/email`);
    }
}