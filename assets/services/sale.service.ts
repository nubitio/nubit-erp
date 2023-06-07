import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";

export class SaleService {

    voidSale(saleId: string, reason: string) {
        return httpClient.post<any>(`/sale/void/${saleId}`, {reason});
    }

    makeInvoice(saleId: string) {
        return httpClient.post<AppResponse>(`/sale/invoice/${saleId}`);
    }

    makeNote(noteId: string) {
        return httpClient.post<any>(`/credit-note/send/${noteId}`);
    }
}

export const saleService = new SaleService();
