import { businessHttpClient } from '@shared/api/network';
import AppResponse from '@shared/api/AppResponse';
import { API_BASE_URL } from '@app/constants';

class SaleService {
  getOne(saleId: string) {
    return businessHttpClient.get<any>(`${API_BASE_URL}sales/${saleId}`);
  }

  voidSale(saleId: string, reason: string, restoreStock = false) {
    return businessHttpClient.post<AppResponse>(`${API_BASE_URL}sales/${saleId}/void`, {
      saleId,
      reason,
      restoreStock,
    });
  }

  makeInvoice(saleId: string) {
    return businessHttpClient.post<AppResponse>(
      `${API_BASE_URL}sales/${saleId}/invoice`,
      {
        saleId,
      },
    );
  }

  printInvoice(saleId: string) {
    return businessHttpClient.get<ArrayBuffer>(
      `${API_BASE_URL}sales/${saleId}/print-invoice`,
      {
        responseType: 'arraybuffer',
      },
    );
  }

  makeNote(noteId: string) {
    return businessHttpClient.post<any>(`${API_BASE_URL}sales/${noteId}/credit-note`);
  }
}

export const saleService = new SaleService();
