import {fromPromise} from "rxjs/internal/observable/innerFrom";
import axios from "axios";
import {map, Observable} from "rxjs";
import {ProductDto} from "../dto/product.dto";

export class ProductService {

    getProducts(warehouse: String): Observable<ProductDto[]> {
        return fromPromise(axios.get<ProductDto[]>(`/api/warehouse/${warehouse}/product`))
            .pipe(map((response) => response.data));
    }
}