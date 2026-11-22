import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import type { OriginLine } from './creditNoteIssueModel';

const API = getCoreApiBaseUrl();
const BASE = `${API}credit-notes`;

export interface EligibleDocument {
  id: string;
  fullNumber: string;
  documentType: '01' | '03';
  issueDate: string;
  customerName: string | null;
  customerDocument: string | null;
  currency: string;
  total: string;
}

export interface NextNumber {
  series: string;
  number: string;
}

export interface SavedReturnLine {
  lineNumber: number;
  quantity: string;
  restock: boolean;
}

export interface CreditNoteDraft {
  id: string;
  relatedTaxDocument: string;
  relatedFullNumber: string | null;
  reasonCode: string | null;
  reasonDescription: string | null;
  lines: SavedReturnLine[];
  updatedAt: string;
}

export interface DraftInput {
  id?: string;
  relatedTaxDocument: string;
  reasonCode: string;
  reasonDescription: string;
  lines: SavedReturnLine[];
}

export async function searchEligibleDocuments(query: string, signal?: AbortSignal) {
  const { data } = await httpClient.get<EligibleDocument[]>(`${BASE}/eligible-documents`, {
    params: { q: query },
    signal,
  });
  return data;
}

export async function fetchNextNumber(documentId: string) {
  const { data } = await httpClient.get<NextNumber>(`${BASE}/next-number`, {
    params: { documentId },
  });
  return data;
}

export async function listDrafts() {
  return (await httpClient.get<CreditNoteDraft[]>(`${BASE}/drafts`)).data;
}

export async function fetchDraft(id: string) {
  return (await httpClient.get<CreditNoteDraft>(`${BASE}/drafts/${id}`)).data;
}

export async function saveDraft(input: DraftInput) {
  return (await httpClient.put<CreditNoteDraft>(`${BASE}/drafts`, input)).data;
}

export async function deleteDraft(id: string) {
  await httpClient.delete(`${BASE}/drafts/${id}`);
}

export async function fetchPreviewPdf(input: Omit<DraftInput, 'id'>) {
  const { data } = await httpClient.post<Blob>(`${BASE}/preview`, input, { responseType: 'blob' });
  return data;
}

export type { OriginLine };
