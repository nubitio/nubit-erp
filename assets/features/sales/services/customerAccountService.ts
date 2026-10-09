import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export interface CollectOnAccountInput {
  customerId: number;
  amount: number;
  paymentMethodId: number;
  paidAt?: string;
  reference?: string;
  /** Empty => FIFO across every open sale, oldest first. */
  applications?: Array<{ saleId: string; amount: number }>;
}

export interface CollectOnAccountApplication {
  saleId: string;
  document: string;
  applied: string;
  balanceDueAfter: string;
}

export interface CollectOnAccountResult {
  collectionId: string;
  customerId: number;
  amount: string;
  currency: string;
  applications: CollectOnAccountApplication[];
}

export interface ReceiveAdvanceInput {
  customerId: number;
  amount: number;
  paymentMethodId: number;
  paidAt?: string;
  reference?: string;
}

export interface CustomerAdvanceReceipt {
  advanceId: string;
  customerId: number;
  amount: string;
  currency: string;
  availableAdvance: string;
}

export interface ApplyAdvanceInput {
  customerId: number;
  amount: number;
  paidAt?: string;
  applications?: Array<{ saleId: string; amount: number }>;
}

export interface AdvanceApplicationResult {
  applicationId: string;
  customerId: number;
  applied: string;
  currency: string;
  availableAdvance: string;
  applications: CollectOnAccountApplication[];
}

const round2 = (n: number) => Number(n.toFixed(2));

export const customerAccountService = {
  collectOnAccount(input: CollectOnAccountInput) {
    return httpClient.post<CollectOnAccountResult>(`${API}sales/collect-on-account`, {
      customerId: input.customerId,
      amount: round2(input.amount),
      paymentMethodId: input.paymentMethodId,
      paidAt: input.paidAt,
      reference: input.reference,
      applications: (input.applications ?? []).map((a) => ({
        saleId: a.saleId,
        amount: round2(a.amount),
      })),
    });
  },

  receiveAdvance(input: ReceiveAdvanceInput) {
    return httpClient.post<CustomerAdvanceReceipt>(`${API}sales/customer-advances`, {
      customerId: input.customerId,
      amount: round2(input.amount),
      paymentMethodId: input.paymentMethodId,
      paidAt: input.paidAt,
      reference: input.reference,
    });
  },

  applyAdvance(input: ApplyAdvanceInput) {
    return httpClient.post<AdvanceApplicationResult>(`${API}sales/customer-advances/apply`, {
      customerId: input.customerId,
      amount: round2(input.amount),
      paidAt: input.paidAt,
      applications: (input.applications ?? []).map((a) => ({
        saleId: a.saleId,
        amount: round2(a.amount),
      })),
    });
  },
};

export default customerAccountService;
