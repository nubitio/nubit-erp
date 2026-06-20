import { createEndpoints } from '@shared/api/endpoints';

const priceLists = createEndpoints('price-lists');

export const priceListEndpoints = {
  ...priceLists,
  prices: (id: string | number) => `${priceLists.list()}/${id}/prices`,
  updatePrices: () => `${priceLists.list()}/update`,
  /** Slim price map used by the sale form to auto-fill line item prices. */
  apply: (id: string | number) => `${priceLists.list()}/${id}/apply`,
};
