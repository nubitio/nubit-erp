import { Constants } from '@core/common/Constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = Constants.API_BASE_URL;

export const companyEndpoints = {
  ...createEndpoints('companies'),
  ubigeo: (id: string | number) => `${BASE}ubigeos/${id}`,
};
