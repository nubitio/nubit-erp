import { httpClient } from '../core/http/HttpClient';
import { lastValueFrom } from 'rxjs';
import { Constants } from '../core/common/Constants';

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

  getOne: (id) => {},

  create: (values) => {},

  update: (id, values) => {},

  delete: (id) => {},
};

export default CategoryService;
