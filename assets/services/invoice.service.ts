import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";

export class InvoiceService {

    consultInvoice(saleId: string) {
        return httpClient.get<AppResponse>(`/invoice/${saleId}/consult`);
    }

    sendEmail(saleId: string) {
        return httpClient.post<any>(`/invoice/${saleId}/email`);
    }
}
