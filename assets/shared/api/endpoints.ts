import { Constants } from '@platform/common/Constants';

const BASE = Constants.API_BASE_URL;

/**
 * Creates a typed endpoint map for a REST resource.
 * @example
 *   const saleEndpoints = createEndpoints('sales');
 *   saleEndpoints.list()          // '/api/sales'
 *   saleEndpoints.detail('123')   // '/api/sales/123'
 */
export const createEndpoints = (resource: string) => ({
  list: () => `${BASE}${resource}`,
  detail: (id: string | number) => `${BASE}${resource}/${id}`,
  create: () => `${BASE}${resource}`,
  update: (id: string | number) => `${BASE}${resource}/${id}`,
  delete: (id: string | number) => `${BASE}${resource}/${id}`,
});

export type Endpoints = ReturnType<typeof createEndpoints>;
