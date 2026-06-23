import { ProductDto } from '@features/product/types/product.dto';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequest, httpRequestData } from '@shared/api/network';
import GridData from '@shared/data/GridData';
import ApiResponse from '@shared/api/ApiResponse';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { ProductStockDto } from '@features/product/types/product.stock.dto';
import type { DataRecord } from '@nubitio/core';

class ProductService {
  getAll(loadOptions: DataRecord): Promise<GridData<ProductDto>> {
    const request$ = httpClient.get<ProductDto[]>(`${API}products`, {
      params: loadOptions,
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
        httpClient.get<ProductDto>(`${API}products/${id}`),
      );
    }
    return httpClient.get<ProductDto>(`${API}products/${id}`);
  }

  getProducts(_warehouseId: string) {
    //return httpClient.get<ProductDto[]>(`${API}warehouses/${warehouseId}/product`);
    return httpClient.get<ProductDto[]>(`${API}products?rows=1000`);
  }

  create(_values: unknown): Promise<string> {
    return Promise.resolve('insert');
  }

  getStock(id: string, warehouseId: string) {
    return httpClient.get<ProductStockDto>(
      `${API}products/${id}/stock/${warehouseId}`,
    );
  }

  updateSalePrice(id: string, salePrice: number): Promise<ProductDto> {
    return httpRequestData<ProductDto>(
      httpClient.patch<ProductDto>(`${API}products/${id}`, {
        salePrice: salePrice.toFixed(2),
      }),
    );
  }
}

export const productService = new ProductService();
