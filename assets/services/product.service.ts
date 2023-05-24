import {lastValueFrom} from "rxjs";
import {ProductDto} from "../dto/product.dto";
import {httpClient} from "../core/http/HttpClient";
import GridData from "../components/common/GridData";
import ApiResponse from "../core/http/ApiResponse";
import {ProductStockDto} from "../dto/product.stock.dto";

class ProductService {

    getAll(loadOptions: any): Promise<GridData<ProductDto>> {
        let subscription = httpClient.get<ProductDto[]>(`/api/products`, {params: loadOptions});

        return lastValueFrom(subscription)
            .then((response: ApiResponse<ProductDto[]>) => {
                return {
                    data: response.data,
                    totalCount: Number(response.headers['x-total-count'])
                } as GridData<ProductDto>;
            });
    }

    getProducts(warehouseId: String) {
        //return httpClient.get<ProductDto[]>(`/api/warehouses/${warehouseId}/product`);
        return httpClient.get<ProductDto[]>(`/api/products?rows=1000`);
    }

    create(values: any) {
        return Promise.resolve("insert");
    }

    getStock(id: string) {
        return httpClient.get<ProductStockDto>(`/api/products/${id}/stock`);
    }
}

export const productService = new ProductService();
