import {lastValueFrom} from "rxjs";
import {ProductDto} from "../dto/product.dto";
import {httpClient} from "../core/http/HttpClient";
import GridData from "../types/GridData";
import ApiResponse from "../core/http/ApiResponse";
import {Constants} from "../core/common/Constants";
import {ProductStockDto} from "../dto/product.stock.dto";

class ProductService {
    getAll(loadOptions: any): Promise<GridData<ProductDto>> {
        let subscription = httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}products`, {params: loadOptions});

        return lastValueFrom(subscription)
            .then((response: ApiResponse<ProductDto[]>) => {
                return {
                    data: response.data,
                    totalCount: Number(response.headers['x-total-count'])
                } as GridData<ProductDto>;
            });
    }

    getOne(id: string, asPromise = false) {
        if (asPromise) {
            return lastValueFrom(httpClient.get<ProductDto>(`${Constants.API_BASE_URL}products/${id}`))
                .then((response: ApiResponse<ProductDto>) => {
                    return response.data;
                });
        }
        return httpClient.get<ProductDto>(`${Constants.API_BASE_URL}products/${id}`);
    }

    getProducts(warehouseId: String) {
        //return httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}warehouses/${warehouseId}/product`);
        return httpClient.get<ProductDto[]>(`${Constants.API_BASE_URL}products?rows=1000`);
    }

    create(values: any) {
        return Promise.resolve("insert");
    }

    getStock(id: string, warehouseId: string) {
        return httpClient.get<ProductStockDto>(`${Constants.API_BASE_URL}products/${id}/stock/${warehouseId}`);
    }
}

export const productService = new ProductService();
