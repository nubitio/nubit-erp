import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

const API = getCoreApiBaseUrl();

type Collection<T> = T[] | { member?: T[]; 'hydra:member'?: T[] };

/** Every record of a small collection (branches, series, voucher types), whichever shape it comes in. */
async function listAll<T>(resource: string): Promise<T[]> {
  const { data } = await httpClient.get<Collection<T>>(`${API}${resource}?itemsPerPage=200`);
  return Array.isArray(data) ? data : (data.member ?? data['hydra:member'] ?? []);
}

export interface Branch {
  id: string;
  name: string;
  address: string | null;
  phone: string | null;
  isActive: boolean;
}

export interface BranchInput {
  name: string;
  address: string | null;
  phone: string | null;
  isActive: boolean;
  /** IRI of the company; only sent when creating. */
  company?: string;
}

export interface VoucherType {
  code: string;
  name: string;
  isActive: boolean;
}

export interface VoucherSeries {
  id: number;
  branch: { id: string; name: string };
  voucherType: { code: string; name: string };
  series: string;
  associatedSeries: string | null;
  isActive: boolean;
}

export interface VoucherSeriesInput {
  /** IRIs of the branch and the voucher type. */
  branch: string;
  voucherType: string;
  series: string;
  associatedSeries: string | null;
  isActive: boolean;
}

export const branchIri = (id: string) => `${API}branches/${id}`;
export const companyIri = (id: string) => `${API}companies/${id}`;
export const voucherTypeIri = (code: string) => `${API}voucher-types/${code}`;

export const branchService = {
  list: () => listAll<Branch>('branches'),
  create: (input: BranchInput) => httpClient.post<Branch>(`${API}branches`, input),
  update: (id: string, input: Partial<BranchInput>) =>
    httpClient.patch<Branch>(`${API}branches/${id}`, input),
};

export const voucherTypeService = {
  list: () => listAll<VoucherType>('voucher-types'),
};

export const seriesService = {
  list: () => listAll<VoucherSeries>('voucher-series'),
  create: (input: VoucherSeriesInput) =>
    httpClient.post<VoucherSeries>(`${API}voucher-series`, input),
  update: (id: number, input: Partial<VoucherSeriesInput>) =>
    httpClient.patch<VoucherSeries>(`${API}voucher-series/${id}`, input),
};
