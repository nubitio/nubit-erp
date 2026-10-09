import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubitio/crud';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  numberField,
  enumField,
  textareaField,
  textField,
} from '@nubitio/crud';
import { productService } from '@features/product';
import { ProductDto } from '@features/product/types/product.dto';
import type { TenantRuntimeConfig } from '@shared/hooks';
import { activeEntityField, activeOnlyFilter } from '@shared/lib/activeEntityField';

type InboundDetailRow = DataRecord & {
  product?: ProductDto;
  qty?: number | string;
  unitCost?: number;
  totalAmount?: number;
};

type FieldChangeContext = {
  defaultSetCellValue: (
    row: InboundDetailRow,
    value: unknown,
    currentRow: InboundDetailRow,
  ) => void;
};
import NumberUtils from '@shared/lib/NumberUtils';
import { SharedUtils } from '@shared/lib/SharedUtils';
import type { DataRecord, GridCellContext } from '@nubitio/crud';

export const InboundFields = (
  branchId: string | number = '',
  runtimeConfig?: TenantRuntimeConfig,
) => {
  const fields: Field[] = [];

  const defaultWarehouseId = runtimeConfig?.defaults.warehouse?.id ?? '';
  const showWarehouseSelector = runtimeConfig?.ui.inventory.showWarehouseSelector ?? true;

  fields.push(identityField().label('ID').build());
  fields.push(
    textField()
      .name('branch')
      .label('Sucursal')
      .defaultValue('/api/branches/' + branchId)
      .hidden(true)
      .visible(false)
      .visibleOnForm(false)
      .build(),
  );
  fields.push(
    dateField()
      .name('issuedAt')
      .label('Fecha')
      .width(130)
      .formatter((cell: GridCellContext) =>
        SharedUtils.dateFormatter(cell.value as string | Date | null | undefined),
      )
      .build(),
  );
  const warehouseField = entityField(API + 'warehouses', 'id', 'name')
    .name('warehouse')
    .label('Almacén')
    .width(200)
    .filters([
      {
        field: 'branch',
        operator: '=',
        value: branchId,
      },
      ...activeOnlyFilter(),
    ])
    .required(true);

  if (!showWarehouseSelector && defaultWarehouseId) {
    warehouseField
      .defaultValue(`${API}warehouses/${defaultWarehouseId}`)
      .hidden(true)
      .visible(false)
      .visibleOnForm(false);
  }

  fields.push(warehouseField.build());
  fields.push(
    activeEntityField(API + 'suppliers', 'id', 'businessName')
      .name('supplier')
      .label('Proveedor')
      .width(240)
      .required(true)
      .build(),
  );
  fields.push(
    enumField([
      { value: '01', text: 'Factura' },
      { value: '03', text: 'Boleta' },
      { value: '07', text: 'Nota de crédito' },
      { value: '08', text: 'Nota de débito' },
    ])
      .name('supplierDocType')
      .label('Tipo comprobante')
      .width(150)
      .defaultValue('01')
      .required(true)
      .build(),
  );
  fields.push(
    textField()
      .name('supplierDocSeries')
      .label('Serie proveedor')
      .width(120)
      .defaultValue('E001')
      .required(true)
      .build(),
  );
  fields.push(
    textField()
      .name('supplierDocNumber')
      .label('Número proveedor')
      .width(140)
      .required(true)
      .build(),
  );
  fields.push(textareaField().name('note').label('Glosa').width(300).build());
  fields.push(
    currencyField()
      .name('grandTotal')
      .label('Total')
      .width(140)
      .defaultValue(0)
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number))
      .readonly(true)
      .build(),
  );

  return fields;
};

export const PurchasesFields = InboundFields;

export const InboundDetailFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(API + 'products', 'id', 'name')
      .name('product')
      .label('Producto')
      .minWidth(200)
      .onChange(async function (
        this: FieldChangeContext,
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as InboundDetailRow;
        const currentRow = currentRowData as InboundDetailRow;
        const product = (await productService.getOne(String(value), true)) as ProductDto;
        row.product = product;
        row.unitCost = 0;
        this.defaultSetCellValue(row, product, currentRow);
      })
      .sortable(false)
      .required(true)
      .build(),
  );
  fields.push(
    numberField()
      .name('qty')
      .label('Cantidad')
      .defaultValue(0)
      .align('center')
      .onChange(function (
        this: FieldChangeContext,
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as InboundDetailRow;
        const currentRow = currentRowData as InboundDetailRow;
        if (value === '') return;
        row.totalAmount = Number(currentRow.unitCost ?? 0) * Number(value ?? 0);
        this.defaultSetCellValue(row, value, currentRow);
      })
      .validators([
        {
          type: 'range',
          options: { min: 0.001, message: 'La cantidad debe ser mayor que cero' },
        },
      ])
      .required(true)
      .sortable(false)
      .valueType('number')
      .build(),
  );
  fields.push(
    currencyField()
      .name('unitCost')
      .label('Precio unitario')
      .defaultValue(0)
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number))
      .onChange(function (
        this: FieldChangeContext,
        rowData: DataRecord,
        value: unknown,
        currentRowData: DataRecord,
      ) {
        const row = rowData as InboundDetailRow;
        const currentRow = currentRowData as InboundDetailRow;
        if (value === '') return;
        row.totalAmount = Number(currentRow.qty ?? 0) * Number(value ?? 0);
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sortable(false)
      .build(),
  );
  fields.push(
    currencyField()
      .name('totalAmount')
      .label('Precio total')
      .defaultValue(0)
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number))
      .sortable(false)
      .filterable(false)
      .build(),
  );

  return fields;
};

export const PurchasesDetailFields = InboundDetailFields;
