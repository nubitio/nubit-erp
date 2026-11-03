import { httpClient } from '@shared/api/HttpClient';
import AppResponse from '@shared/api/AppResponse';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

class TaxDocumentService {
  consultDocument(documentId: string) {
    return httpClient.get<AppResponse>(`${API}tax-documents/${documentId}/consult`);
  }

  sendDocument(documentId: string) {
    return httpClient.post<AppResponse>(`${API}tax-documents/${documentId}/send`);
  }

  validateDocument(documentId: string) {
    return httpClient.post<AppResponse>(`${API}tax-documents/${documentId}/validate`);
  }

  /** `email` overrides the customer's address, e.g. one the cashier just collected. */
  sendEmail(documentId: string, email?: string) {
    return httpClient.post<AppResponse>(
      `${API}tax-documents/${documentId}/email`,
      email ? { email } : undefined,
    );
  }
}

export const taxDocumentService = new TaxDocumentService();
