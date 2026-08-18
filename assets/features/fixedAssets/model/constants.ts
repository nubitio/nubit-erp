import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';

export const fixedAssetEvents = createCrudEvents('fixed-asset');
export const fixedAssetApiUrl = `${API}fixed-assets`;

export { fixedAssetFormLayout } from './formLayout';
