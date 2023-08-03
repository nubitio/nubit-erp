import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";
import {Constants} from "../core/common/Constants";

export class SaleService {
    getOne(saleId: string) {
        return httpClient.get<any>(`${Constants.API_BASE_URL}sales/${saleId}`);
    }

    voidSale(saleId: string, reason: string) {
        return httpClient.post<AppResponse>(`/sale/void/${saleId}`, {reason});
    }

    makeInvoice(saleId: string) {
        return httpClient.post<AppResponse>(`/sale/invoice/${saleId}`);
    }

    makeNote(noteId: string) {
        return httpClient.post<any>(`/credit-note/send/${noteId}`);
    }

    cloneSale(saleId: string) {
        return httpClient.post<AppResponse>(`/sale/clone/${saleId}`);
    }
}

export const saleService = new SaleService();
