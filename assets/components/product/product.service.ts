import {lastValueFrom, Observable} from "rxjs";
import {ProductDto} from "./product.dto";
import {httpClient} from "../../core/http/HttpClient";
import GridData from "../common/GridData";
import ApiResponse from "../../core/http/ApiResponse";

export class ProductService {

    getAll(loadOptions: any): Promise<GridData<ProductDto>> {
        console.log(loadOptions);
        let result = httpClient.get<ProductDto[]>(`/product/get`, {params: loadOptions});

        return lastValueFrom(result)
            .then((response: ApiResponse<ProductDto[]>) => {
                return {
                    data: response.data,
                    totalCount: 32
                } as GridData<ProductDto>;
            });
    }

    getProducts(warehouse: String): Observable<ApiResponse<ProductDto[]>> {
        return httpClient.get<ProductDto[]>(`/api/warehouse/${warehouse}/product`);
    }

    insert(values: any) {
        return Promise.resolve("insert");
    }
}