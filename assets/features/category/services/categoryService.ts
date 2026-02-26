import { httpClient } from '../../../shared/api/HttpClient';
import { lastValueFrom } from 'rxjs';
import { Constants } from '../../../core/common/Constants';

const CategoryService = {
  getAll: (loadOptions) => {
    return lastValueFrom(
      httpClient.get(`${Constants.API_BASE_URL}categories`, { params: loadOptions }),
    ).then((response) => {
      return {
        data: response.data,
        totalCount: Number(response.headers['x-total-count']),
      };
    });
  },

  getOne: (_id) => {},

  create: (_values) => {},

  update: (_id, _values) => {},

  delete: (_id) => {},
};

export default CategoryService;
