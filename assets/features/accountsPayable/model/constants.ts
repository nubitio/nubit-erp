import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const supplierBillEvents = createCrudEvents('supplier-bill');
export const supplierBillApiUrl = `${API}supplier-bills`;

export { supplierBillFormLayout } from './formLayout';
