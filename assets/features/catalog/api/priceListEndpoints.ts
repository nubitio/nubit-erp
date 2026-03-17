import { Constants } from '@core/common/Constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = Constants.API_BASE_URL;

export const priceListEndpoints = {
  ...createEndpoints('price-lists'),
  prices: (id: string | number) => `${BASE}price-lists/${id}/prices`,
  updatePrices: () => `${BASE}price-lists/update`,
};
