import { MediaDto } from '@shared/api/media.dto';

interface UnitOfMeasureDto {
  id: number;
  name: string;
  ratio: number;
}

export interface ProductDto {
  id: string;
  type: number;
  productType?: 'STOCKABLE' | 'SERVICE' | 'DIGITAL' | 'COMPOSITE' | 'CONSUMABLE' | string;
  productKind?: 'GOOD' | 'SERVICE' | 'DIGITAL' | string;
  inventoryBehavior?: 'TRACKED' | 'NOT_TRACKED' | 'COMPOSITE' | string;
  trackInventory?: boolean;
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
