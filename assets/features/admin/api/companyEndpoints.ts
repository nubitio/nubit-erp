import { API_BASE_URL } from '@app/constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = API_BASE_URL;

export const companyEndpoints = {
  ...createEndpoints('companies'),
  ubigeo: (id: string | number) => `${BASE}ubigeos/${id}`,
};
