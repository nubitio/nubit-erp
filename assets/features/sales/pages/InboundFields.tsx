import { Constants } from '@core/common/Constants';
import { userInformation } from '@core/common/UserInformation';
import { Field } from '@core/field/Field';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  numberField,
  textareaField,
  textField,
} from '@core/field/FieldBuilders';
import { productService } from '@features/product';
import { ProductDto } from '@dto/product.dto';
import NumberUtils from '@shared/lib/NumberUtils';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const InboundFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().label('ID').build());
  fields.push(
    textField()
      .name('branch')
      .label('Sucursal')
      .defaultValue('/api/branches/' + userInformation().currentBranchId)
      .hidden(true)
      .visible(false)
      .visibleOnForm(false)
      .build(),
  );
  fields.push(
    dateField()
      .name('issued_at')
      .label('Fecha')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'warehouses', 'id', 'name')
      .name('warehouse')
      .label('Almacen')
      .byKeyUrl(Constants.API_BASE_URL + 'warehouses')
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: userInformation().currentBranchId,
        },
      ])
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'suppliers', 'id', 'businessName')
      .name('supplier')
      .label('Proveedor')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
      .build(),
  );
  fields.push(textareaField().name('note').label('Glosa').build());
  fields.push(
    currencyField()
      .name('grand_total')
      .label('Total')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
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
    entityField(Constants.API_BASE_URL + 'products', 'id', 'name')
      .name('product')
      .label('Producto')
      .minWidth(200)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        const product = (await productService.getOne(value, true)) as ProductDto;
        row.product = product;
        row.unit_cost = 0;
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
      .align('center')
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.total_amount = currentRow.unit_cost * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .required(true)
      .sortable(false)
      .valueType('number')
      .build(),
  );
  fields.push(
    currencyField()
      .name('unit_cost')
      .label('Precio unitario')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.total_amount = currentRow.qty * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sortable(false)
      .build(),
  );
  fields.push(
    currencyField()
      .name('total_amount')
      .label('Precio total')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .sortable(false)
      .filterable(false)
      .build(),
  );

  return fields;
};

export const PurchasesDetailFields = InboundDetailFields;
