import {httpClient} from '../core/http/HttpClient';
import AppResponse from '../core/http/AppResponse';
import {Constants} from '../core/common/Constants';

export class TaxDocumentService {
  consultDocument(documentId: string) {
    return httpClient.get<AppResponse>(`${Constants.API_BASE_URL}tax-documents/${documentId}/consult`);
  }

  sendDocument(documentId: string) {
    return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}tax-documents/${documentId}/send`);
  }

  validateDocument(documentId: string) {
    return httpClient.post<AppResponse>(`${Constants.API_BASE_URL}tax-documents/${documentId}/validate`);
  }

  sendEmail(documentId: string) {
    return httpClient.post<any>(`${Constants.API_BASE_URL}tax-documents/${documentId}/email`);
  }
}

export const taxDocumentService = new TaxDocumentService();
