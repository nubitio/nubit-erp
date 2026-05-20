import { businessHttpClient } from '@shared/api/network';
import AppResponse from '@shared/api/AppResponse';
import { API_BASE_URL } from '@app/constants';

class TaxDocumentService {
  consultDocument(documentId: string) {
    return businessHttpClient.get<AppResponse>(
      `${API_BASE_URL}tax-documents/${documentId}/consult`,
    );
  }

  sendDocument(documentId: string) {
    return businessHttpClient.post<AppResponse>(
      `${API_BASE_URL}tax-documents/${documentId}/send`,
    );
  }

  validateDocument(documentId: string) {
    return businessHttpClient.post<AppResponse>(
      `${API_BASE_URL}tax-documents/${documentId}/validate`,
    );
  }

  sendEmail(documentId: string) {
    return businessHttpClient.post<any>(
      `${API_BASE_URL}tax-documents/${documentId}/email`,
    );
  }
}

export const taxDocumentService = new TaxDocumentService();
