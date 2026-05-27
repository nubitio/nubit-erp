import { createEndpoints } from '@shared/api/endpoints';

const companies = createEndpoints('companies');

export const companyEndpoints = {
  ...companies,
  ubigeo: (id: string | number) => `${companies.list()}/ubigeos/${id}`,
};
