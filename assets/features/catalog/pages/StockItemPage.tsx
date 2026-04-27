import { SmartCrudPage, defineResource } from '@core/crud';
import { defineFields } from '@core/crud/fieldContract';
import { Constants } from '@core/common/Constants';

interface StockItemRecord extends Record<string, unknown> {
  id: string;
  warehouse: { id?: string; name?: string } | string;
  product: { id?: string; code?: string | null; name?: string } | string;
  qtyOnHand: string;
  qtyReserved: string;
  qtyAvailable: string;
  updatedAt: string;
}

const formatProduct = (value: unknown) => {
  if (value && typeof value === 'object') {
    const product = value as { code?: string | null; name?: string };
    return [product.code, product.name].filter(Boolean).join(' - ');
  }

  return String(value ?? '');
};

const formatWarehouse = (value: unknown) => {
  if (value && typeof value === 'object') {
    return String((value as { name?: string }).name ?? '');
  }

  return String(value ?? '');
};

const formatQty = (value: unknown) => {
  const numeric = Number(value ?? 0);

  return Number.isFinite(numeric)
    ? numeric.toLocaleString('es-PE', { maximumFractionDigits: 3, minimumFractionDigits: 0 })
    : '';
};

const stockItemResource = defineResource<StockItemRecord>({
  id: 'stock-item',
  title: 'Existencias',
  apiUrl: Constants.API_BASE_URL + 'stock-items',
  fields: [],
  fieldContract: defineFields<StockItemRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'id', patch: { visible: false, visibleOnForm: false } },
      {
        kind: 'override',
        key: 'warehouse',
        patch: {
          order: 0,
          width: 180,
          label: 'Almacén',
          valueField: '_iri',
          textField: 'name',
          url: Constants.API_BASE_URL + 'warehouses',
          selectedFilterOperation: '=',
          formatter: (e) => formatWarehouse(e.value),
        },
      },
      {
        kind: 'override',
        key: 'product',
        patch: {
          order: 1,
          width: 320,
          label: 'Producto',
          valueField: '_iri',
          textField: 'name',
          url: Constants.API_BASE_URL + 'products',
          selectedFilterOperation: '=',
          formatter: (e) => formatProduct(e.value),
        },
      },
      {
        kind: 'override',
        key: 'qtyOnHand',
        patch: { order: 2, width: 130, label: 'Stock actual', align: 'right', formatter: (e) => formatQty(e.value) },
      },
      {
        kind: 'override',
        key: 'qtyReserved',
        patch: { order: 3, width: 130, label: 'Reservado', align: 'right', formatter: (e) => formatQty(e.value) },
      },
      {
        kind: 'override',
        key: 'qtyAvailable',
        patch: { order: 4, width: 130, label: 'Disponible', align: 'right', formatter: (e) => formatQty(e.value) },
      },
      { kind: 'override', key: 'updatedAt', patch: { order: 5, width: 170, label: 'Actualizado' } },
    ],
  }),
  sort: [{ selector: 'updatedAt', desc: true }],
  permissions: {
    canAdd: false,
    canEdit: false,
    canDelete: false,
  },
});

export const StockItemPage = () => <SmartCrudPage resource={stockItemResource} />;
