import { httpClient } from '@shared/api/HttpClient';
import { purchaseOrderEndpoints } from '../api/purchaseOrderEndpoints';

const purchaseOrderService = () => {
  const send = (id: string) => {
    return httpClient.patch(purchaseOrderEndpoints.detail(id), { status: 'sent' });
  };

  const cancel = (id: string) => {
    return httpClient.delete(purchaseOrderEndpoints.detail(id));
  };

  return { send, cancel };
};

export default purchaseOrderService;
