import { createEndpoints } from '@shared/api/endpoints';

// createEndpoints already resolves the base URL from CoreConfig via getCoreApiBaseUrl().
const sales = createEndpoints('sales');

export const saleEndpoints = {
  ...sales,
  items: (id: string | number) => `${sales.list()}/${id}/items`,
};
