import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type SupplierBillPayResponse = unknown;

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

  return { pay };
};

export default supplierBillService;
