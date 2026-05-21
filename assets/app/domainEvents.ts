// eFact domain CRUD event namespaces.
// These are eFact-specific and must not live in @nubit/core.
import { createCrudEvents } from '@nubit/core';

export const branchEvents        = createCrudEvents('branch');
export const categoryEvents      = createCrudEvents('category');
export const companyEvents       = createCrudEvents('company');
export const inboundEvents       = createCrudEvents('inbound');
export const operationEvents     = createCrudEvents('operation');
export const paymentMethodEvents = createCrudEvents('payment-methods');
export const permissionEvents    = createCrudEvents('permission');
export const priceEvents         = createCrudEvents('price');
export const priceListEvents     = createCrudEvents('price-list');
export const roleEvents          = createCrudEvents('role');
export const sunatCatalogEvents  = createCrudEvents('sunat-catalog');
export const supplierEvents      = createCrudEvents('supplier');
export const unitOfMeasureEvents = createCrudEvents('unit-of-measure');
export const userEvents          = createCrudEvents('user');
export const voucherSeriesEvents = createCrudEvents('voucher-series');
export const voucherTypeEvents   = createCrudEvents('voucherType');
export const warehouseEvents     = createCrudEvents('warehouse');
