import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

const API = getCoreApiBaseUrl();

export interface PleLedgerRow {
  index: number;
  issueDate: string;
  documentType: string;
  series: string;
  number: string;
  counterpartyDoc: string;
  counterpartyName: string;
  taxable: number;
  igv: number;
  total: number;
  state: string;
  stateLabel: string;
}

export interface PleLedgerTotals {
  taxable: number;
  igv: number;
  total: number;
}

export type PleBookStatus = 'ready' | 'empty' | 'unavailable';

export interface PleBookPreview {
  key: string;
  pleCode: string;
  label: string;
  bookCode: string | null;
  filename: string | null;
  recordCount: number;
  sizeBytes: number;
  status: PleBookStatus;
  statusLabel: string;
  rows: PleLedgerRow[];
  totals: PleLedgerTotals;
}

export interface PlePreviewResponse {
  period: string;
  generatedAt: string;
  books: {
    sales: PleBookPreview;
    purchases: PleBookPreview;
    diary: PleBookPreview;
    ledger: PleBookPreview;
  };
}

export type PleDownloadBook = 'sales' | 'purchases';

export interface PleValidationIssue {
  severity: 'error' | 'warning';
  row: number | null;
  code: string;
  message: string;
}

export interface PleBookValidation {
  valid: boolean;
  issueCount: number;
  issues: PleValidationIssue[];
}

export interface PleValidationResponse {
  period: string;
  valid: boolean;
  validatedAt: string;
  books: Partial<Record<PleDownloadBook, PleBookValidation>>;
}

function getFilenameFromDisposition(disposition: string | undefined, fallback: string): string {
  if (!disposition) return fallback;

  const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1].replace(/"/g, ''));

  const match = /filename="?([^";]+)"?/i.exec(disposition);
  return match?.[1] ?? fallback;
}

function downloadBlob(blob: Blob, filename: string): void {
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}

export function currentPeriod(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = `${now.getMonth() + 1}`.padStart(2, '0');
  return `${year}${month}`;
}

export function isValidPeriod(period: string): boolean {
  if (!/^\d{6}$/.test(period)) return false;
  const month = Number(period.slice(4));
  return month >= 1 && month <= 12;
}

export function periodLabel(period: string): string {
  const year = Number(period.slice(0, 4));
  const month = Number(period.slice(4)) - 1;
  const date = new Date(year, month, 1);
  return date.toLocaleDateString('es-PE', { month: 'long', year: 'numeric' });
}

export function formatFileSize(bytes: number): string {
  if (bytes <= 0) return '0 B';
  if (bytes < 1024) return `${bytes} B`;
  return `${(bytes / 1024).toFixed(1)} KB`;
}

export function formatMoney(value: number): string {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

class PleService {
  preview(period: string): Promise<PlePreviewResponse> {
    return httpClient
      .get<PlePreviewResponse>(`${API}v1/ple/preview?period=${period}`)
      .then((response) => response.data);
  }

  validate(period: string, book?: PleDownloadBook): Promise<PleValidationResponse> {
    const bookQuery = book ? `&book=${book}` : '';
    return httpClient
      .get<PleValidationResponse>(`${API}v1/ple/validate?period=${period}${bookQuery}`)
      .then((response) => response.data);
  }

  async download(book: PleDownloadBook, period: string): Promise<void> {
    const response = await httpClient.get<Blob>(`${API}v1/ple/${book}?period=${period}`, {
      responseType: 'blob',
    });
    const fallback = `LE_${book}_${period}.txt`;
    downloadBlob(
      response.data,
      getFilenameFromDisposition(response.headers['content-disposition'], fallback),
    );
  }

  async downloadZip(period: string): Promise<void> {
    const response = await httpClient.get<Blob>(`${API}v1/ple/zip?period=${period}`, {
      responseType: 'blob',
    });
    downloadBlob(
      response.data,
      getFilenameFromDisposition(response.headers['content-disposition'], `PLE_${period}.zip`),
    );
  }
}

export const pleService = new PleService();