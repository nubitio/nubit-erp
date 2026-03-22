import { businessHttpClient } from '@shared/api/network';
import AppResponse from '@shared/api/AppResponse';
import { Constants } from '@core/common/Constants';

export class TaxDocumentService {
  consultDocument(documentId: string) {
    return businessHttpClient.get<AppResponse>(
      `${Constants.API_BASE_URL}tax-documents/${documentId}/consult`,
    );
  }

  sendDocument(documentId: string) {
    return businessHttpClient.post<AppResponse>(
      `${Constants.API_BASE_URL}tax-documents/${documentId}/send`,
    );
  }

  validateDocument(documentId: string) {
    return businessHttpClient.post<AppResponse>(
      `${Constants.API_BASE_URL}tax-documents/${documentId}/validate`,
    );
  }

  sendEmail(documentId: string) {
    return businessHttpClient.post<any>(
      `${Constants.API_BASE_URL}tax-documents/${documentId}/email`,
    );
  }
}

export const taxDocumentService = new TaxDocumentService();
