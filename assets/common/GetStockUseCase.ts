import {productService} from "../service/product.service";
import {lastValueFrom} from "rxjs";

export const GetStockUseCase = async (productId: string) => {
    let session = JSON.parse(localStorage.getItem('session') ?? '{}');
    let warehouseId = session['warehouse_id'];
    return await lastValueFrom(productService.getStock(productId, warehouseId)).then(
        (response) => response.data
    )
}
