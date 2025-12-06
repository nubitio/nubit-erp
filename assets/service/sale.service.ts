import { httpClient } from '../core/http/HttpClient';
import AppResponse from '../core/http/AppResponse';
import { Constants } from '../core/common/Constants';

export class SaleService {
  getOne(saleId: string) {
    return httpClient.get<any>(`${Constants.API_BASE_URL}movements/${saleId}`);
  }

  voidSale(saleId: string, reason: string) {
    return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}sales/${saleId}/void`, {
      saleId,
      reason,
    });
  }

  makeInvoice(saleId: string) {
    return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}sales/${saleId}/invoice`, {
      saleId,
    });
  }

  printInvoice(saleId: string) {
    return httpClient.get<any>(`${Constants.API_BASE_URL}sales/${saleId}/print-invoice`);
  }

  makeNote(noteId: string) {
    return httpClient.post<any>(`${Constants.API_BASE_URL}sales/${noteId}/credit-note`);
  }
}

export const saleService = new SaleService();
