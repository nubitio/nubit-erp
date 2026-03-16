import { Constants } from '@platform/common/Constants';
import { userInformation } from '@platform/common/UserInformation';
import { Field } from '@platform/field/Field';
import {
  identityField,
  entityField,
  dateField,
  numberField,
  textareaField,
} from '@platform/field/FieldBuilders';
import { productService } from '@features/product';
import { ProductDto } from '@dto/product.dto';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const OutboundFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    numberField()
      .name('tipo_id')
      .label('Tipo id')
      .defaultValue(2)
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
          value: 2,
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
      //.defaultValue(userInformation().currentWarehouseId)
      .build(),
  );
  fields.push(textareaField().name('glosa').label('Glosa').build());

  return fields;
};

export const OutboundDetailFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'products', 'id', 'name')
      .name('producto')
      .label('Producto')
      .minWidth(250)
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
    numberField()
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
