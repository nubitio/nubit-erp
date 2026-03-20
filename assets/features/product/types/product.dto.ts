import { MediaDto } from '@shared/api/media.dto';
import { UnitOfMeasureDto } from '@features/unitOfMeasure/types/UnitOfMeasureDto';

export interface ProductDto {
  id: string;
  type: number;
  photo: MediaDto;
  code: string;
  name: string;
  salePrice: number;
  category: string;
  unitOfMeasure: UnitOfMeasureDto;
  detractionCatalog?: {
    id: number;
    code: string;
    description: string;
    percentage: number | null;
  } | null;
}
