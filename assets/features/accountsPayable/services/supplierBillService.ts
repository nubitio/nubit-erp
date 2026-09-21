import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type SupplierBillPayResponse = unknown;

export interface SupplierBillSummary {
  supplier?: { businessName?: string };
  number?: string | null;
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
  amount: number;
  paymentMethodId: number;
  paidAt?: string;
  reference?: string;
}

const supplierBillService = () => {
  const pay = (id: string, input: PaySupplierBillInput) => {
    return httpClient.post<SupplierBillPayResponse>(API + `supplier-bills/${id}/pay`, {
      id,
      amount: Number(input.amount.toFixed(2)),
      paymentMethodId: input.paymentMethodId,
      paidAt: input.paidAt,
      reference: input.reference,
    });
  };

  const get = (id: string) => httpClient.get<SupplierBillSummary>(API + `supplier-bills/${id}`);

  const reverse = (id: string, paymentId: number) =>
    httpClient.post<SupplierBillSummary>(API + `supplier-bills/${id}/reverse-payment`, {
      id,
      paymentId,
    });

  return { pay, get, reverse };
};

export default supplierBillService;
