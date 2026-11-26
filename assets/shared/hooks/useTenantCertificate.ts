import { useMutation } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';

export interface TenantCertificateSummary {
  id: number;
  ruc: string;
  status: string;
  subjectCn: string | null;
  issuerCn: string | null;
  serialNumber: string | null;
  fingerprintSha256: string;
  validFrom: string;
  expiresAt: string;
  uploadedAt: string;
  lycetSyncStatus: string;
}

export interface UploadTenantCertificateInput {
  ruc?: string;
  solUser: string;
  solPass: string;
  currentPassword: string;
  certificate: File;
  certificatePassword?: string;
}

export interface UploadTenantCertificateResponse {
  certificate: TenantCertificateSummary;
}

export interface TenantCertificateResponse {
  certificate: TenantCertificateSummary | null;
}

export async function getTenantCertificate() {
  return httpRequestData(httpClient.get<TenantCertificateResponse>('/api/tenant/certificate'));
}

async function uploadTenantCertificate(input: UploadTenantCertificateInput) {
  const formData = new FormData();
  if (input.ruc) {
    formData.append('ruc', input.ruc);
  }
  formData.append('solUser', input.solUser);
  formData.append('solPass', input.solPass);
  formData.append('currentPassword', input.currentPassword);
  if (input.certificatePassword) {
    formData.append('certificatePassword', input.certificatePassword);
  }
  formData.append('certificate', input.certificate);

  return httpRequestData(
    httpClient.post<UploadTenantCertificateResponse>('/api/tenant/certificate', formData, {
      errorPresentation: 'inline',
    }),
  );
}

export function useUploadTenantCertificate() {
  return useMutation<UploadTenantCertificateResponse, Error, UploadTenantCertificateInput>({
    mutationFn: uploadTenantCertificate,
  });
}
