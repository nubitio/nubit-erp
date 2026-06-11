import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

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
    return httpClient.get<SetupStatus>(API + 'setup');
  }

  complete(payload: SetupPayload) {
    return httpClient.post<SetupStatus>(API + 'setup', payload);
  }
}

export const setupService = new SetupService();
