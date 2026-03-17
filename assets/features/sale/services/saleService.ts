import { httpClient } from '@shared/api/HttpClient';
import AppResponse from '@shared/api/AppResponse';
import { Constants } from '@core/common/Constants';

export class SaleService {
  getOne(saleId: string) {
    return httpClient.get<any>(`${Constants.API_BASE_URL}sales/${saleId}`);
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
    return httpClient.get<ArrayBuffer>(`${Constants.API_BASE_URL}sales/${saleId}/print-invoice`, {
      responseType: 'arraybuffer',
    });
  }

  makeNote(noteId: string) {
    return httpClient.post<any>(`${Constants.API_BASE_URL}sales/${noteId}/credit-note`);
  }
}

export const saleService = new SaleService();
