import {httpClient} from "../core/http/HttpClient";

export class InvoiceService {

    consultInvoice(saleId: string) {
        return httpClient.get<any>(`/invoice/${saleId}/consult`);
    }

    sendEmail(saleId: string) {
        return httpClient.post<any>(`/invoice/${saleId}/email`);
    }
}