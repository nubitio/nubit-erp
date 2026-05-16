import type { LoadOptions } from 'devextreme/data/load_options';
import { ProductDto } from '@features/product/types/product.dto';
import { businessHttpClient, httpRequest, httpRequestData } from '@shared/api/network';
import GridData from '@shared/data/GridData';
import ApiResponse from '@shared/api/ApiResponse';
import { Constants } from '@core/common/Constants';
import { ProductStockDto } from '@features/product/types/product.stock.dto';

class ProductService {
  getAll(loadOptions: LoadOptions): Promise<GridData<ProductDto>> {
    const request$ = businessHttpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}products`, {
      params: loadOptions as Record<string, unknown>,
    });

    return httpRequest(request$).then((response: ApiResponse<ProductDto[]>) => {
      return {
        data: response.data,
        totalCount: Number(response.headers['x-total-count']),
      } as GridData<ProductDto>;
    });
  }

  getOne(id: string, asPromise = false) {
    if (asPromise) {
      return httpRequestData<ProductDto>(
        businessHttpClient.get<ProductDto>(`${Constants.API_BASE_URL}products/${id}`),
      );
    }
    return businessHttpClient.get<ProductDto>(`${Constants.API_BASE_URL}products/${id}`);
  }

  getProducts(_warehouseId: string) {
    //return httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}warehouses/${warehouseId}/product`);
    return businessHttpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}products?rows=1000`);
  }

  create(_values: unknown): Promise<string> {
    return Promise.resolve('insert');
  }

  getStock(id: string, warehouseId: string) {
    return businessHttpClient.get<ProductStockDto>(
      `${Constants.API_BASE_URL}products/${id}/stock/${warehouseId}`,
    );
  }
}

export const productService = new ProductService();
