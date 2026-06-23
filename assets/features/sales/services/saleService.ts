import { httpClient } from '@shared/api/HttpClient';
import AppResponse from '@shared/api/AppResponse';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

class SaleService {
  getOne(saleId: string) {
    return httpClient.get<unknown>(`${API}sales/${saleId}`);
  }

  voidSale(saleId: string, reason: string, restoreStock = false) {
    return httpClient.post<AppResponse>(`${API}sales/${saleId}/void`, {
      saleId,
      reason,
      restoreStock,
    });
  }

  makeInvoice(saleId: string) {
    return httpClient.post<AppResponse>(
      `${API}sales/${saleId}/invoice`,
      {
        saleId,
      },
    );
  }

  printInvoice(saleId: string) {
    return httpClient.get<ArrayBuffer>(
      `${API}sales/${saleId}/print-invoice`,
      {
        responseType: 'arraybuffer',
      },
    );
  }

  makeNote(noteId: string) {
    return httpClient.post<unknown>(`${API}sales/${noteId}/credit-note`);
  }

  replaceRejected(saleId: string) {
    return httpClient.post<AppResponse>(`${API}sales/${saleId}/replace-rejected`);
  }
}

export const saleService = new SaleService();
