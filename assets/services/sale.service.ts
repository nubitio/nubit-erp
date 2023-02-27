import {httpClient} from "../core/http/HttpClient";

export class SaleService {

    voidSale(saleId: string, reason: string) {
        return httpClient.post<any>(`/sale/void/${saleId}`, {reason});
    }

    makeInvoice(saleId: string) {
        return httpClient.post<any>(`/sale/invoice/${saleId}`);
    }
}