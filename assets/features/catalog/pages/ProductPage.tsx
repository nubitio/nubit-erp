import { SmartCrudPage, defineResource } from '@core/crud';
import type { FieldOverride } from '@core/crud/hydra/useResourceSchema';
import { Constants } from '@core/common/Constants';

const productResource = defineResource({
  id: 'product',
  title: 'Productos',
  apiUrl: Constants.API_BASE_URL + 'products',
  fields: [],
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
  sort: [{ selector: 'name', desc: false }],
});

const productFieldOverrides: FieldOverride[] = [
  { key: 'code', order: 0, width: 140 },
  { key: 'type', order: 1, width: 140 },
  { key: 'sku', order: 2, width: 140 },
  { key: 'barcode', order: 3, width: 160 },
  { key: 'purchasePrice', order: 4, width: 130 },
  { key: 'stock', order: 5, width: 120 },
  { key: 'name', order: 6, width: 260 },
  { key: 'category', order: 7, width: 180 },
  { key: 'salePrice', order: 8, width: 130 },
  { key: 'productType', visible: false },
  { key: 'productClass', visible: false },
  { key: 'inventoryBehavior', visible: false },
  { key: 'controlInventory', visible: false },
  { key: 'batchTracking', visible: false },
  { key: 'baseUnit', visible: false },
  { key: 'unitOfMeasure', visible: false },
  { key: 'stockMinimum', visible: false },
  { key: 'stocks', visible: false },
  { key: 'photo', visible: false },
  { key: 'detractionCatalog', visible: false },
];

export const ProductPage = () => (
  <SmartCrudPage resource={productResource} fieldOverrides={productFieldOverrides} />
);
