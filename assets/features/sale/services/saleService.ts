import { businessHttpClient } from '@shared/api/network';
import AppResponse from '@shared/api/AppResponse';
import { Constants } from '@core/common/Constants';

export class SaleService {
  getOne(saleId: string) {
    return businessHttpClient.get<any>(`${Constants.API_BASE_URL}sales/${saleId}`);
  }

  voidSale(saleId: string, reason: string) {
    return businessHttpClient.post<AppResponse>(`${Constants.API_BASE_URL}sales/${saleId}/void`, {
      saleId,
      reason,
    });
  }

  makeInvoice(saleId: string) {
    return businessHttpClient.post<AppResponse>(
      `${Constants.API_BASE_URL}sales/${saleId}/invoice`,
      {
        saleId,
      },
    );
  }

  printInvoice(saleId: string) {
    return businessHttpClient.get<ArrayBuffer>(
      `${Constants.API_BASE_URL}sales/${saleId}/print-invoice`,
      {
        responseType: 'arraybuffer',
      },
    );
  }

  makeNote(noteId: string) {
    return businessHttpClient.post<any>(`${Constants.API_BASE_URL}sales/${noteId}/credit-note`);
  }
}

export const saleService = new SaleService();
