import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type SaleCollectPaymentResponse = unknown;

export interface CollectSalePaymentInput {
  amount: number;
  paymentMethodId: number;
  paidAt?: string;
  reference?: string;
}

const saleCollectService = () => {
  const collect = (id: string, input: CollectSalePaymentInput) => {
    return httpClient.post<SaleCollectPaymentResponse>(API + `sales/${id}/collect`, {
      id,
      amount: Number(input.amount.toFixed(2)),
      paymentMethodId: input.paymentMethodId,
      paidAt: input.paidAt,
      reference: input.reference,
    });
  };

  return { collect };
};

export default saleCollectService;
