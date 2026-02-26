import { Constants } from '@platform/common/Constants';
import { userInformation } from '@platform/common/UserInformation';
import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { productService } from '@features/product';
import { ProductDto } from '@dto/product.dto';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const OutboundFields = () => {
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
      .type(FieldType.NUMBER)
      .name('tipo_id')
      .label('Tipo id')
      .defaultValue(2)
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
          value: 2,
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
      //.defaultValue(userInformation().currentWarehouseId)
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXTAREA).name('glosa').label('Glosa').build());

  return fields;
};

export const OutboundDetailFields = () => {
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
      .minWidth(250)
      .url(Constants.API_BASE_URL + 'products')
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        const product = (await productService.getOne(value, true)) as ProductDto;
        row.producto = product;
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
      .sortable(false)
      .required(true)
      .valueType('number')
      .build(),
  );

  return fields;
};
