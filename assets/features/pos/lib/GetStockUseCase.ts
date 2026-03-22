import { productService } from '../../product';
import { httpRequestData } from '@shared/api/network';

export const GetStockUseCase = async (productId: string, warehouseId: string) => {
  return httpRequestData(productService.getStock(productId, warehouseId));
};
