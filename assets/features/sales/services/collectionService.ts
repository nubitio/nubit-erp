import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export interface CollectionTenderInput {
  paymentMethodId: number;
  amount: string;
  /** Treasury account the money lands in; omitted = the payment method's mapped account. */
  moneyAccountId?: string;
  reference?: string;
}

export interface CollectionAllocationInput {
  saleId: string;
  amount: string;
}

export interface RegisterCollectionInput {
  customerId: number;
  currency: string;
  tenders: CollectionTenderInput[];
  /** Empty => oldest due date first, decided by the server. */
  allocations?: CollectionAllocationInput[];
  /** Same key on a retry of the same collection: the server returns the first result. */
  idempotencyKey: string;
}

export interface CollectionAllocationResult {
  installmentId: string;
  saleId: string;
  document: string;
  amount: string;
  balanceDueAfter: string;
}

export interface CollectionResult {
  collectionId: string;
  customerId: number;
  currency: string;
  total: string;
  /** What settled no invoice and stays as the customer's advance. */
  unapplied: string;
  allocations: CollectionAllocationResult[];
  tenders: Array<{ tenderId: string; paymentMethodId: number; amount: string }>;
  replayed: boolean;
}

export interface CollectionHistoryItem {
  id: string;
  receivedOn: string;
  currency: string;
  total: string;
  unapplied: string;
  status: 'confirmed' | 'reversed';
  tenders: Array<{ method: string; amount: string }>;
}

export const collectionService = {
  register(input: RegisterCollectionInput) {
    return httpClient.post<CollectionResult>(
      `${API}sales/collections`,
      {
        customerId: input.customerId,
        currency: input.currency,
        tenders: input.tenders.map((t) => ({
          paymentMethodId: t.paymentMethodId,
          amount: t.amount,
          moneyAccountId: t.moneyAccountId || undefined,
          reference: t.reference || undefined,
        })),
        allocations: input.allocations ?? [],
      },
      { headers: { 'Idempotency-Key': input.idempotencyKey } },
    );
  },

  async history(customerId: number, signal?: AbortSignal): Promise<CollectionHistoryItem[]> {
    const { data } = await httpClient.get<CollectionHistoryItem[]>(
      `${API}sales/reports/receivable-customers/${customerId}/collections?limit=10`,
      { signal },
    );
    return Array.isArray(data) ? data : [];
  },

  reverse(collectionId: string) {
    return httpClient.post<{ collectionId: string; alreadyReversed: boolean }>(
      `${API}sales/collections/${collectionId}/reverse`,
      {},
    );
  },
};

export default collectionService;
