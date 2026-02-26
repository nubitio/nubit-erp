import { Constants } from '@platform/common/Constants';
import { userInformation } from '@platform/common/UserInformation';
import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { productService } from '@features/product';
import { ProductDto } from '@dto/product.dto';
import NumberUtils from '@shared/lib/NumberUtils';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const InboundFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('ID')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('tipo_id')
      .label('Tipo id')
      .defaultValue(1)
      .hidden(true)
      .visible(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.DATE)
      .name('fecha')
      .label('Fecha')
      .defaultValue(new Date())
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('operacion')
      .label('Operacion')
      .url(Constants.API_BASE_URL + 'logistic-operations')
      .filters([
        {
          field: 'type',
          operator: '=',
          value: 1,
        },
      ])
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('almacen')
      .label('Almacen')
      .url(Constants.API_BASE_URL + 'warehouses')
      .byKeyUrl(Constants.API_BASE_URL + 'warehouses')
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: userInformation().currentBranchId,
        },
      ])
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('proveedor')
      .label('Proveedor')
      .url(Constants.API_BASE_URL + 'suppliers')
      .valueField('id')
      .textField('businessName')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXTAREA).name('glosa').label('Glosa').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('total')
      .label('Total')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .readonly(true)
      .sendAsString(true)
      .build(),
  );

  return fields;
};

export const InboundDetailFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('producto')
      .label('Producto')
      .minWidth(200)
      .url(Constants.API_BASE_URL + 'products')
      .valueField('id')
      .textField('name')
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
    new FieldBuilder()
      .type(FieldType.NUMBER)
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
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('precio_unitario')
      .label('Precio unitario')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.precio_total = currentRow.cantidad * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .align('right')
      .sendAsString(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('precio_total')
      .label('Precio total')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .sortable(false)
      .filterable(false)
      .sendAsString(true)
      .build(),
  );

  return fields;
};
