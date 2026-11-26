import { httpClient } from '@shared/api/HttpClient';
import { purchaseOrderEndpoints, withPurchasingBranch } from '../api/purchaseOrderEndpoints';

const purchaseOrderService = (branchId = '') => {
  const send = (id: string) => {
    return httpClient.patch(withPurchasingBranch(purchaseOrderEndpoints.detail(id), branchId), {
      status: 'sent',
    });
  };

  const cancel = (id: string) => {
    return httpClient.delete(withPurchasingBranch(purchaseOrderEndpoints.detail(id), branchId));
  };

  return { send, cancel };
};

export default purchaseOrderService;
