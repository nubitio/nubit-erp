import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";

export class InvoiceService {
    sendInvoice(saleId: string) {
        return httpClient.post<AppResponse>(`/invoice/${saleId}/send`);
    }

    consultInvoice(saleId: string) {
        return httpClient.get<AppResponse>(`/invoice/${saleId}/consult`);
    }

    validateInvoice(saleId: string) {
        return httpClient.get<AppResponse>(`/invoice/${saleId}/validate`);
    }

    sendEmail(saleId: string) {
        return httpClient.post<any>(`/invoice/${saleId}/email`);
    }
}

export const invoiceService = new InvoiceService();
