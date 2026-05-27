import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubit/crud';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  numberField,
  textareaField,
  textField,
} from '@nubit/crud';
import { productService } from '@features/product';
import { ProductDto } from '@features/product/types/product.dto';
import NumberUtils from '@shared/lib/NumberUtils';
import { SharedUtils } from '@shared/lib/SharedUtils';
import type { GridCellContext } from '@nubit/crud';

export const InboundFields = (branchId: string | number = '') => {
  const fields: Field[] = [];

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
      .formatter((cell: GridCellContext) => SharedUtils.dateFormatter(cell.value as string | Date | null | undefined))
      .build(),
  );
  fields.push(
    entityField(API + 'warehouses', 'id', 'name')
      .name('warehouse')
      .label('Almacen')
      .width(200)
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: branchId,
        },
      ])
      .required(true)
      .build(),
  );
  fields.push(
    entityField(API + 'suppliers', 'id', 'businessName')
      .name('supplier')
      .label('Proveedor')
      .width(240)
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
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        const product = (await productService.getOne(value, true)) as ProductDto;
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
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
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
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
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
