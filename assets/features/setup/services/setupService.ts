import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';

export type SetupStatus = {
  configured: boolean;
  userCount: number;
  companyCount: number;
  branchCount: number;
};

export type SetupPayload = {
  adminUsername: string;
  adminPassword: string;
  companyRuc: string;
  companyBusinessName: string;
  companyTradeName?: string | null;
  companyFiscalAddress: string;
  companyPhone?: string | null;
  branchName: string;
  branchAddress?: string | null;
  branchPhone?: string | null;
};

class SetupService {
  getStatus() {
    return httpClient.get<SetupStatus>(API_BASE_URL + 'setup');
  }

  complete(payload: SetupPayload) {
    return httpClient.post<SetupStatus>(API_BASE_URL + 'setup', payload);
  }
}

export const setupService = new SetupService();
