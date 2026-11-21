import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';

const API = getCoreApiBaseUrl();
const companies = createEndpoints('companies');

export const LOGO_MAX_BYTES = 2 * 1024 * 1024;
export const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

export const BANKS = ['BCP', 'BBVA', 'INTERBANK', 'SCOTIABANK'] as const;

export interface CompanyLogo {
  '@id'?: string;
  id?: string;
  /** Public address of the image. */
  path?: string;
}

/** The tenant's company as the API serves it. */
export interface Company {
  id: string;
  ruc: string;
  businessName: string;
  tradeName: string | null;
  fiscalAddress: string;
  phone: string | null;
  logo: CompanyLogo | null;
  ubigeoCode: string | null;
  department: string | null;
  province: string | null;
  district: string | null;
  bankName: string | null;
  bankAccount: string | null;
  bankCci: string | null;
  detraccionAccount: string | null;
}

/** What can be written; `logo` is the IRI of an uploaded media, or `null` to remove it. */
export type CompanyUpdate = Partial<Omit<Company, 'id' | 'logo'>> & { logo?: string | null };

export const companyService = {
  get: (id: string) => httpClient.get<Company>(companies.detail(id)),

  update: (id: string, patch: CompanyUpdate) =>
    httpClient.patch<Company>(companies.update(id), patch),

  uploadLogo: (file: File) => {
    const body = new FormData();
    body.append('file', file);
    return httpClient.post<CompanyLogo>(`${API}media`, body);
  },
};

/** The IRI a company's `logo` takes, from what the media upload answered. */
export function logoIri(media: CompanyLogo): string | null {
  if (media['@id']) return media['@id'];
  return media.id ? `${API}media/${media.id}` : null;
}
