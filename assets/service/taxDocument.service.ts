import {httpClient} from '../core/http/HttpClient';
import AppResponse from '../core/http/AppResponse';
import {Constants} from '../core/common/Constants';

export class TaxDocumentService {
  consultInvoice(saleId: string) {
    return httpClient.get<AppResponse>(`${Constants.API_BASE_URL}tax-documents/${saleId}/consult`);
  }

  sendInvoice(saleId: string) {
    return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}tax-documents/${saleId}/send`);
  }

  validateInvoice(saleId: string) {
    return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}tax-documents/${saleId}/validate`);
  }

  sendEmail(saleId: string) {
    return httpClient.post<any>(`${Constants.API_BASE_URL}tax-documents/${saleId}/email`);
  }
}

export const taxDocumentService = new TaxDocumentService();
