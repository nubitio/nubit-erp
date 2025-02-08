import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";
import {Constants} from "../core/common/Constants";

export class InvoiceService {
    consultInvoice(saleId: string) {
        return httpClient.get<AppResponse>(`${Constants.API_BASE_URL}invoices/${saleId}/consult`);
    }

    sendInvoice(saleId: string) {
        return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}invoices/${saleId}/send`, {saleId});
    }

    validateInvoice(saleId: string) {
        return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}invoices/${saleId}/validate`, {saleId});
    }

    sendEmail(saleId: string) {
        return httpClient.post<any>(`${Constants.API_BASE_URL}invoices/${saleId}/email`, {saleId});
    }
}

export const invoiceService = new InvoiceService();
