import {productService} from "../service/product.service";
import {lastValueFrom} from "rxjs";

export const GetStockUseCase = async (productId: string, warehouseId: string) => {
    return await lastValueFrom(productService.getStock(productId, warehouseId)).then(
        (response) => response.data
    )
}
