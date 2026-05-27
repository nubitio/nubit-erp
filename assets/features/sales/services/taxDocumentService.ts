import { httpClient } from '@shared/api/HttpClient';
import AppResponse from '@shared/api/AppResponse';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

class TaxDocumentService {
  consultDocument(documentId: string) {
    return httpClient.get<AppResponse>(
      `${API}tax-documents/${documentId}/consult`,
    );
  }

  sendDocument(documentId: string) {
    return httpClient.post<AppResponse>(
      `${API}tax-documents/${documentId}/send`,
    );
  }

  validateDocument(documentId: string) {
    return httpClient.post<AppResponse>(
      `${API}tax-documents/${documentId}/validate`,
    );
  }

  sendEmail(documentId: string) {
    return httpClient.post<any>(
      `${API}tax-documents/${documentId}/email`,
    );
  }
}

export const taxDocumentService = new TaxDocumentService();
