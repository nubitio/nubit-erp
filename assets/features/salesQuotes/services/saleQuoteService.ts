import { httpClient } from '@shared/api/HttpClient';
import { saleQuoteEndpoints } from '../api/saleQuoteEndpoints';

interface SaleRecordResponse {
  id: string;
  [key: string]: unknown;
}

const saleQuoteService = () => {
  const send = (id: string) => {
    return httpClient.post(saleQuoteEndpoints.send(id), { id });
  };

  const convert = (
    id: string,
    paymentMethodId?: number,
    warehouseId?: string,
    branchId?: string,
  ) => {
    return httpClient.post<SaleRecordResponse>(saleQuoteEndpoints.convert(id), {
      id,
      paymentMethodId,
      warehouseId,
      branchId,
    });
  };

  return { send, convert };
};

export default saleQuoteService;
