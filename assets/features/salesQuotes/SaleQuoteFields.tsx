import { getCoreApiBaseUrl } from '@nubitio/core';

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
import { SALE_QUOTE_STATUS_LABELS } from './model/constants';

type SaleQuoteDetailRow = DataRecord & {
  product?: ProductDto | null;
  qty?: number | string;
  unitPrice?: number;
  discountPercent?: number;
  netAmount?: number | string;
};

export const SaleQuoteFields = (): Field[] => {
  const fields: Field[] = [];

  fields.push(identityField().build());

  fields.push(
    entityField(API + 'customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .build(),
  );

  fields.push(
    entityField(API + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .build(),
  );

  fields.push(
    entityField(API + 'warehouses', 'id', 'name')
      .name('warehouse')
      .label('Almacén')
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

  fields.push(dateField().name('validUntil').label('Válida hasta').build());

  fields.push(
    enumField(Object.entries(SALE_QUOTE_STATUS_LABELS).map(([value, text]) => ({ value, text })))
      .name('status')
      .label('Estado')
      .disabled(true)
      .build(),
  );

  fields.push(textareaField().name('note').label('Notas').build());

  return fields;
};

export const SaleQuoteDetailFields = (): Field[] => {
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
            row: SaleQuoteDetailRow,
            value: unknown,
            currentRow: SaleQuoteDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as SaleQuoteDetailRow;
        const currentRow = currentRowData as SaleQuoteDetailRow;
        if (value === '' || value == null) {
          row.product = null;
          row.qty = '';
          row.unitPrice = 0;
          row.discountPercent = 0;
          row.netAmount = 0;
          this.defaultSetCellValue(row, null, currentRow);
          return;
        }

        const product = (await productService.getOne(String(value), true)) as ProductDto;
        row.product = product;
        row.qty = 1;
        row.unitPrice = product.salePrice;
        row.discountPercent = 0;
        row.netAmount = product.salePrice;
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
      .name('qty')
      .label('Cantidad')
      .width(92)
      .align('center')
      .valueType('number')
      .precision(2)
      .onChange(function (
        this: {
          defaultSetCellValue: (
            row: SaleQuoteDetailRow,
            value: unknown,
            currentRow: SaleQuoteDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as SaleQuoteDetailRow;
        const currentRow = currentRowData as SaleQuoteDetailRow;
        if (value === '') return;
        recomputeNet(row, Number(value), currentRow.unitPrice, currentRow.discountPercent);
        this.defaultSetCellValue(row, value, currentRow);
      })
      .required(true)
      .sortable(false)
      .build(),
  );

  fields.push(
    currencyField()
      .name('unitPrice')
      .label('Precio')
      .width(110)
      .align('right')
      .defaultValue(0)
      .onChange(function (
        this: {
          defaultSetCellValue: (
            row: SaleQuoteDetailRow,
            value: unknown,
            currentRow: SaleQuoteDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as SaleQuoteDetailRow;
        const currentRow = currentRowData as SaleQuoteDetailRow;
        recomputeNet(row, currentRow.qty, Number(value), currentRow.discountPercent);
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sortable(false)
      .build(),
  );

  fields.push(
    numberField()
      .name('discountPercent')
      .label('Desc. %')
      .width(90)
      .align('center')
      .valueType('number')
      .precision(2)
      .onChange(function (
        this: {
          defaultSetCellValue: (
            row: SaleQuoteDetailRow,
            value: unknown,
            currentRow: SaleQuoteDetailRow,
          ) => void;
        },
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as SaleQuoteDetailRow;
        const currentRow = currentRowData as SaleQuoteDetailRow;
        recomputeNet(row, currentRow.qty, currentRow.unitPrice, Number(value));
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sortable(false)
      .build(),
  );

  fields.push(
    currencyField()
      .name('netAmount')
      .label('Neto')
      .width(110)
      .align('right')
      .disabled(true)
      .sortable(false)
      .build(),
  );

  return fields;
};

function recomputeNet(
  row: SaleQuoteDetailRow,
  qty: number | string | undefined,
  unitPrice: number | undefined,
  discountPercent: number | undefined,
): void {
  const q = Number(qty ?? 0);
  const price = Number(unitPrice ?? 0);
  const pct = Number(discountPercent ?? 0);
  const gross = q * price;
  const discount = Number((gross * (pct / 100)).toFixed(2));
  row.netAmount = Number((gross - discount).toFixed(2));
}
