import { API_BASE_URL } from '@app/constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = API_BASE_URL;

export const saleEndpoints = {
  ...createEndpoints('sales'),
  items: (id: string | number) => `${BASE}sales/${id}/items`,
};
