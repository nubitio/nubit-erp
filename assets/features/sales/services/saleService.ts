import { httpClient } from '@shared/api/HttpClient';
import AppResponse from '@shared/api/AppResponse';
import { API_BASE_URL } from '@app/constants';

class SaleService {
  getOne(saleId: string) {
    return httpClient.get<any>(`${API_BASE_URL}sales/${saleId}`);
  }

  voidSale(saleId: string, reason: string, restoreStock = false) {
    return httpClient.post<AppResponse>(`${API_BASE_URL}sales/${saleId}/void`, {
      saleId,
      reason,
      restoreStock,
    });
  }

  makeInvoice(saleId: string) {
    return httpClient.post<AppResponse>(
      `${API_BASE_URL}sales/${saleId}/invoice`,
      {
        saleId,
      },
    );
  }

  printInvoice(saleId: string) {
    return httpClient.get<ArrayBuffer>(
      `${API_BASE_URL}sales/${saleId}/print-invoice`,
      {
        responseType: 'arraybuffer',
      },
    );
  }

  makeNote(noteId: string) {
    return httpClient.post<any>(`${API_BASE_URL}sales/${noteId}/credit-note`);
  }

  replaceRejected(saleId: string) {
    return httpClient.post<AppResponse>(`${API_BASE_URL}sales/${saleId}/replace-rejected`);
  }
}

export const saleService = new SaleService();
