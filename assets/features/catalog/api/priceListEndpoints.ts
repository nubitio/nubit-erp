import { API_BASE_URL } from '@app/constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = API_BASE_URL;

export const priceListEndpoints = {
  ...createEndpoints('price-lists'),
  prices: (id: string | number) => `${BASE}price-lists/${id}/prices`,
  updatePrices: () => `${BASE}price-lists/update`,
};
