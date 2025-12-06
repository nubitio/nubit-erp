import { MediaDto } from './media.dto';
import { UnitOfMeasureDto } from './UnitOfMeasureDto';

export interface ProductDto {
  id: string;
  type: number;
  photo: MediaDto;
  code: string;
  name: string;
  salePrice: number;
  category: string;
  unitOfMeasure: UnitOfMeasureDto;
}
