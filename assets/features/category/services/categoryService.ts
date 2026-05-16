import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@core/common/Constants';

type LoadOptions = Record<string, unknown>;

const CategoryService = {
  getAll: (loadOptions: LoadOptions) => {
    return httpClient.get(`${Constants.API_BASE_URL}categories`, { params: loadOptions }).then((response) => {
      return {
        data: response.data,
        totalCount: Number(response.headers['x-total-count']),
      };
    });
  },

  getOne: (_id: unknown) => {},

  create: (_values: unknown) => {},

  update: (_id: unknown, _values: unknown) => {},

  delete: (_id: unknown) => {},
};

export default CategoryService;
