import { createEndpoints } from '@shared/api/endpoints';

// PurchaseOrderItem has no standalone sub-resource — items are embedded
// directly in the parent PurchaseOrder's read groups, unlike Purchase's
// /purchases/{id}/items sub-resource.
export const purchaseOrderEndpoints = createEndpoints('purchase-orders');

export const withPurchasingBranch = (url: string, branchId: string) =>
  branchId ? `${url}?${new URLSearchParams({ branchId })}` : url;
