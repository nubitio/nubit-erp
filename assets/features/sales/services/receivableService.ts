import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export interface ReceivableInvoicePayment {
  method_name?: string | null;
  amount?: string;
  paid_at?: string | null;
  reference?: string | null;
}

export interface ReceivableInvoice {
  id: string;
  invoiceFullNumber: string | null;
  issuedDate: string | null;
  dueDate: string | null;
  currency: string;
  grandTotal: string;
  paidTotal: string;
  balanceDue: string;
  status: string;
  payments: ReceivableInvoicePayment[];
}

function collectionMembers(payload: unknown): Record<string, unknown>[] {
  if (Array.isArray(payload)) return payload as Record<string, unknown>[];

  if (payload && typeof payload === 'object') {
    const record = payload as Record<string, unknown>;
    const member = record['hydra:member'] ?? record['member'];
    if (Array.isArray(member)) return member as Record<string, unknown>[];
  }

  return [];
}

/**
 * Open (unpaid) invoices for one customer, oldest due first. Backs the customer
 * drawer — the aggregated debtors endpoint only carries per-customer totals.
 */
export async function listOpenInvoices(
  customerId: number,
  signal?: AbortSignal,
): Promise<ReceivableInvoice[]> {
  const response = await httpClient.get<unknown>(`${API}sales`, {
    signal,
    params: {
      filter: [
        ['receivableAccount', '=', 1],
        ['customer', '=', customerId],
        ['status', '<>', 'VOIDED'],
        ['balanceDue', '>', 0],
      ],
      sort: [{ selector: 'dueDate', desc: false }],
      itemsPerPage: 200,
    },
  });

  return collectionMembers(response.data).map((raw) => ({
    id: String(raw['id'] ?? ''),
    invoiceFullNumber: (raw['invoiceFullNumber'] as string | null) ?? null,
    issuedDate: (raw['issuedDate'] as string | null) ?? null,
    dueDate: (raw['dueDate'] as string | null) ?? null,
    currency: String(raw['currency'] ?? 'PEN'),
    grandTotal: String(raw['grandTotal'] ?? '0'),
    paidTotal: String(raw['paidTotal'] ?? '0'),
    balanceDue: String(raw['balanceDue'] ?? '0'),
    status: String(raw['status'] ?? ''),
    payments: Array.isArray(raw['payments']) ? (raw['payments'] as ReceivableInvoicePayment[]) : [],
  }));
}
