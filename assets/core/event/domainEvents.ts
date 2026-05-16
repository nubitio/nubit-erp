/**
 * Central registry of domain CRUD events for simple features that have no
 * other model logic.  Collocated here with createCrudEvents so there is a
 * single place to add/remove feature event namespaces.
 */
import { createCrudEvents } from './createCrudEvents';

export const branchEvents        = createCrudEvents('branch');
export const categoryEvents      = createCrudEvents('category');
export const companyEvents       = createCrudEvents('company');
export const configEvents        = createCrudEvents('config');
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
