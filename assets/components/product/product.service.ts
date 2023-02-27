import {lastValueFrom} from "rxjs";
import {ProductDto} from "./product.dto";
import {httpClient} from "../../core/http/HttpClient";
import GridData from "../common/GridData";
import ApiResponse from "../../core/http/ApiResponse";

export class ProductService {

    getAll(loadOptions: any): Promise<GridData<ProductDto>> {
        let subscription = httpClient.get<ProductDto[]>(`/product/get`, {params: loadOptions});

        return lastValueFrom(subscription)
            .then((response: ApiResponse<ProductDto[]>) => {
                return {
                    data: response.data,
                    totalCount: 32
                } as GridData<ProductDto>;
            });
    }

    getProducts(warehouseId: String) {
        //return httpClient.get<ProductDto[]>(`/api/warehouses/${warehouseId}/product`);
        return httpClient.get<ProductDto[]>(`/api/products?rows=1000`);
    }

    insert(values: any) {
        return Promise.resolve("insert");
    }
}