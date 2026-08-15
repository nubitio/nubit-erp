import { httpClient } from '@shared/api/HttpClient';

interface UploadResponse {
  importId: string;
  status: string;
  topic: string;
}

interface AutoMatchResponse {
  id: string;
}

const bankStatementImportService = () => {
  const upload = (file: File, bankAccountId: string, bankProfile: string) => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('bankAccountId', bankAccountId);
    formData.append('bankProfile', bankProfile);

    return httpClient.post<UploadResponse>('/api/v1/bank-statement-imports', formData);
  };

  const autoMatch = (importId: string) => {
    return httpClient.post<AutoMatchResponse>(
      `/api/bank-statement-imports/${importId}/auto-match`,
      {
        id: importId,
      },
    );
  };

  return { upload, autoMatch };
};

export default bankStatementImportService;
