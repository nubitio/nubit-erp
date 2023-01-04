import {Observable} from "rxjs";
import {ProductDto} from "../dto/product.dto";
import {httpClient} from "../core/utils/HttpClient";

export class ProductService {

    getProducts(warehouse: String): Observable<ProductDto[]> {
        return httpClient.get<ProductDto[]>(`/api/warehouse/${warehouse}/product`);
    }
}