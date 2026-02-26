import { productService } from '../features/product';
import { lastValueFrom } from 'rxjs';

export const GetStockUseCase = async (productId: string, warehouseId: string) => {
  return await lastValueFrom(productService.getStock(productId, warehouseId)).then(
    (response) => response.data,
  );
};
