import {httpClient} from "../utils/HttpClient";
import {Observable} from "rxjs";

export class InvoiceService {

    consultInvoice(saleId: string): Observable<any> {
        return httpClient.get(`/invoice/consult/${saleId}`);
    }
}