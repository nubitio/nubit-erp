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
    numberField()
      .name('tipo_id')
      .label('Tipo id')
      .defaultValue(1)
      .hidden(true)
      .visible(false)
      .build(),
  );
  fields.push(
    dateField()
      .name('fecha')
      .label('Fecha')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'logistic-operations', 'id', 'name')
      .name('operacion')
      .label('Operacion')
      .filters([
        {
          field: 'type',
          operator: '=',
          value: 1,
        },
      ])
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'warehouses', 'id', 'name')
      .name('almacen')
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
      .name('proveedor')
      .label('Proveedor')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
      .build(),
  );
  fields.push(textareaField().name('glosa').label('Glosa').build());
  fields.push(
    currencyField()
      .name('total')
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
      .name('producto')
      .label('Producto')
      .minWidth(200)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        const product = (await productService.getOne(value, true)) as ProductDto;
        row.producto = product;
        row.precio_unitario = 0;
        this.defaultSetCellValue(row, product, currentRow);
      })
      .sortable(false)
      .required(true)
      .build(),
  );
  fields.push(
    numberField()
      .name('cantidad')
      .label('Cantidad')
      .align('center')
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.precio_total = currentRow.precio_unitario * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .required(true)
      .sortable(false)
      .valueType('number')
      .build(),
  );
  fields.push(
    currencyField()
      .name('precio_unitario')
      .label('Precio unitario')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.precio_total = currentRow.cantidad * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sortable(false)
      .build(),
  );
  fields.push(
    currencyField()
      .name('precio_total')
      .label('Precio total')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .sortable(false)
      .filterable(false)
      .build(),
  );

  return fields;
};

export const PurchasesDetailFields = InboundDetailFields;
