import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type SupplierBillPayResponse = unknown;

export interface SupplierBillSummary {
  supplier?: { businessName?: string };
  number?: string | null;
  series?: string | null;
  documentType?: string | null;
  issueDate?: string;
  dueDate?: string;
  amount?: string;
  paidTotal?: string;
  currency?: string;
  balanceDue?: string;
  status?: string;
  payments?: SupplierBillPayment[];
}

export interface SupplierBillPayment {
  id: number;
  amount: string;
  paidAt: string;
  reference?: string | null;
  reversedAt?: string | null;
}

export interface PaySupplierBillInput {
  amount: string;
  paymentMethodId: number;
  paidAt?: string;
  reference?: string;
}

const supplierBillService = () => {
  const pay = (id: string, input: PaySupplierBillInput) => {
    return httpClient.post<SupplierBillPayResponse>(
      API + `supplier-bills/${id}/pay`,
      {
        id,
        amount: input.amount,
        paymentMethodId: input.paymentMethodId,
        paidAt: input.paidAt,
        reference: input.reference,
      },
      { errorPresentation: 'inline' },
    );
  };

  const get = (id: string, signal?: AbortSignal) =>
    httpClient.get<SupplierBillSummary>(API + `supplier-bills/${id}`, {
      signal,
      errorPresentation: 'inline',
    });

  const reverse = (id: string, paymentId: number) =>
    httpClient.post<SupplierBillSummary>(
      API + `supplier-bills/${id}/reverse-payment`,
      {
        id,
        paymentId,
      },
      { errorPresentation: 'inline' },
    );

  return { pay, get, reverse };
};

export default supplierBillService;
