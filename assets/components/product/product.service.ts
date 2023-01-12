import {lastValueFrom, Observable} from "rxjs";
import {ProductDto} from "./product.dto";
import {httpClient} from "../../core/utils/HttpClient";
import GridData from "../common/GridData";

export class ProductService {

    getAll(loadOptions: any): Promise<GridData<ProductDto>> {
        console.log(loadOptions);
        let result = httpClient.get<ProductDto[]>(`/product/get`, {params: loadOptions});

        return lastValueFrom(result)
            .then((products: ProductDto[]) => {
                return {
                    data: products,
                    totalCount: 32
                } as GridData<ProductDto>;
            });
    }

    getProducts(warehouse: String): Observable<ProductDto[]> {
        return httpClient.get<ProductDto[]>(`/api/warehouse/${warehouse}/product`);
    }

    insert(values: any) {
        return Promise.resolve("insert");
    }
}