import { Constants } from '@core/common/Constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = Constants.API_BASE_URL;

export const saleEndpoints = {
  ...createEndpoints('sales'),
  items: (id: string | number) => `${BASE}sales/${id}/items`,
};
