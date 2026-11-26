import { getCoreApiBaseUrl } from '@nubitio/core';
import { withPurchasingBranch } from './api/purchaseOrderEndpoints';

const API = getCoreApiBaseUrl();
import {
  currencyField,
  dateField,
  entityField,
  enumField,
  identityField,
  numberField,
  textareaField,
  type DataRecord,
  type Field,
  type GridCellContext,
} from '@nubitio/crud';
import { productService } from '@features/product';
import type { ProductDto } from '@features/product/types/product.dto';
import { PURCHASE_ORDER_STATUS_LABELS } from './model/constants';

type PurchaseOrderDetailRow = DataRecord & {
  product?: ProductDto | null;
  orderedQty?: number | string;
  expectedUnitCost?: number;
  totalAmount?: number | string;
};

export const PurchaseOrderFields = (branchId = ''): Field[] => {
  const fields: Field[] = [];

  fields.push(identityField().build());

  fields.push(
    entityField(API + 'suppliers', 'id', 'businessName')
      .name('supplier')
      .label('Proveedor')
      .required(true)
      .build(),
  );

  fields.push(
    entityField(API + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .visibleOnForm(false)
      .build(),
  );

  fields.push(
    entityField(
      branchId ? withPurchasingBranch(API + 'purchasing/warehouses', branchId) : API + 'warehouses',
      branchId ? '_iri' : 'id',
      'name',
    )
      .name('warehouse')
      .label('Almacén destino')
      .build(),
  );

  fields.push(
    enumField([
      { value: 'PEN', text: 'Soles (PEN)' },
      { value: 'USD', text: 'Dólares (USD)' },
    ])
      .name('currency')
      .label('Moneda')
      .defaultValue('PEN')
      .build(),
  );

  fields.push(dateField().name('expectedDate').label('Fecha esperada').build());

  fields.push(
    enumField(
      Object.entries(PURCHASE_ORDER_STATUS_LABELS).map(([value, text]) => ({ value, text })),
    )
      .name('status')
      .label('Estado')
      .disabled(true)
      .build(),
  );

  fields.push(textareaField().name('note').label('Notas').build());

  return fields;
};

export const PurchaseOrderDetailFields = (): Field[] => {
  const fields: Field[] = [];

  fields.push(identityField().build());

  fields.push(
    entityField(API + 'products', 'id', 'name')
      .name('product')
      .label('Producto')
      .minWidth(300)
      .onChange(async function (
        this: {
          defaultSetCellValue: (
            row: PurchaseOrderDetailRow,
            value: unknown,
            currentRow: PurchaseOrderDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as PurchaseOrderDetailRow;
        const currentRow = currentRowData as PurchaseOrderDetailRow;
        if (value === '' || value == null) {
          row.product = null;
          row.orderedQty = '';
          row.expectedUnitCost = 0;
          row.totalAmount = 0;
          this.defaultSetCellValue(row, null, currentRow);
          return;
        }

        const product = (await productService.getOne(String(value), true)) as ProductDto;
        row.product = product;
        row.orderedQty = 1;
        row.expectedUnitCost = 0;
        row.totalAmount = 0;
        this.defaultSetCellValue(row, product, currentRow);
      })
      .formatter((cell: GridCellContext) => {
        const row = cell.data as { nameSnapshot?: string } | undefined;
        const product = cell.value as { name?: string } | undefined;
        return row?.nameSnapshot || product?.name;
      })
      .required(true)
      .sortable(false)
      .build(),
  );

  fields.push(
    numberField()
      .name('orderedQty')
      .label('Cantidad')
      .width(100)
      .align('center')
      .valueType('number')
      .precision(3)
      .onChange(function (
        this: {
          defaultSetCellValue: (
            row: PurchaseOrderDetailRow,
            value: unknown,
            currentRow: PurchaseOrderDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as PurchaseOrderDetailRow;
        const currentRow = currentRowData as PurchaseOrderDetailRow;
        if (value === '') return;
        row.totalAmount = Number(
          (Number(value) * Number(currentRow.expectedUnitCost ?? 0)).toFixed(2),
        );
        this.defaultSetCellValue(row, value, currentRow);
      })
      .required(true)
      .sortable(false)
      .build(),
  );

  fields.push(
    currencyField()
      .name('expectedUnitCost')
      .label('Costo esperado')
      .width(120)
      .align('right')
      .defaultValue(0)
      .onChange(function (
        this: {
          defaultSetCellValue: (
            row: PurchaseOrderDetailRow,
            value: unknown,
            currentRow: PurchaseOrderDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as PurchaseOrderDetailRow;
        const currentRow = currentRowData as PurchaseOrderDetailRow;
        row.totalAmount = Number((Number(currentRow.orderedQty ?? 0) * Number(value)).toFixed(2));
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sortable(false)
      .build(),
  );

  fields.push(
    currencyField()
      .name('totalAmount')
      .label('Total')
      .width(120)
      .align('right')
      .disabled(true)
      .sortable(false)
      .build(),
  );

  return fields;
};
