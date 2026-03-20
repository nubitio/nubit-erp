import { lastValueFrom } from 'rxjs';
import type { LoadOptions } from 'devextreme/data/load_options';
import { ProductDto } from '@features/product/types/product.dto';
import { httpClient } from '@shared/api/HttpClient';
import GridData from '../../../types/GridData';
import ApiResponse from '@shared/api/ApiResponse';
import { Constants } from '@core/common/Constants';
import { ProductStockDto } from '@features/product/types/product.stock.dto';

class ProductService {
  getAll(loadOptions: LoadOptions): Promise<GridData<ProductDto>> {
    const subscription = httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}products`, {
      params: loadOptions,
    });

    return lastValueFrom(subscription).then((response: ApiResponse<ProductDto[]>) => {
      return {
        data: response.data,
        totalCount: Number(response.headers['x-total-count']),
      } as GridData<ProductDto>;
    });
  }

  getOne(id: string, asPromise = false) {
    if (asPromise) {
      return lastValueFrom(
        httpClient.get<ProductDto>(`${Constants.API_BASE_URL}products/${id}`),
      ).then((response: ApiResponse<ProductDto>) => {
        return response.data;
      });
    }
    return httpClient.get<ProductDto>(`${Constants.API_BASE_URL}products/${id}`);
  }

  getProducts(_warehouseId: string) {
    //return httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}warehouses/${warehouseId}/product`);
    return httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}products?rows=1000`);
  }

  create(_values: unknown): Promise<string> {
    return Promise.resolve('insert');
  }

  getStock(id: string, warehouseId: string) {
    return httpClient.get<ProductStockDto>(
      `${Constants.API_BASE_URL}products/${id}/stock/${warehouseId}`,
    );
  }
}

export const productService = new ProductService();
