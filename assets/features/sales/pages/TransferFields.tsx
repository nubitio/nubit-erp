import { Constants } from '@platform/common/Constants';
import { Field } from '@platform/field/Field';
import {
  identityField,
  entityField,
  dateField,
  noneField,
  textareaField,
} from '@platform/field/FieldBuilders';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { StorageUtils } from '@shared/lib/StorageUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const TransferFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().name('transfer_id').label('Transfer id').build());
  fields.push(
    dateField()
      .name('fecha')
      .label('Fecha')
      .defaultValue('Today')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  const session = StorageUtils.getItem('session');
  const warehouseUrl = `${Constants.API_BASE_URL}branches/${session['branch_id']}/warehouses`;
  fields.push(
    entityField(warehouseUrl, 'almacen_id', 'descripcion')
      .name('almacen_origen')
      .label('Almacen origen')
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    noneField()
      .name('origen')
      .label('Almacen origen')
      .formatter((cell: ColumnCellTemplateData) => cell.data.almacen_origen)
      .build(),
  );
  fields.push(
    entityField(warehouseUrl, 'almacen_id', 'descripcion')
      .name('almacen_destino')
      .label('Almacen destino')
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    noneField()
      .name('destino')
      .label('Almacen destino')
      .formatter((cell: ColumnCellTemplateData) => cell.data.almacen_destino)
      .build(),
  );
  fields.push(textareaField().width(100).name('nota').label('Nota').build());

  /*detailFields.push(new FieldBuilder()
        .type(FieldType.SELECT)
        .valueField('producto_id')
        .textField('descripcion')
        .name('producto_id')
        .label('Producto')
        .required(true)
        .build());
    detailFields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('cantidad')
        .label('Cantidad')
        .required(true)
        .build());
    */
  return fields;
};
