import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { StorageUtils } from '../../core/utils/StorageUtils';
import { Constants } from '../../core/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { SharedUtils } from '../../core/utils/SharedUtils';

export const TransferFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('transfer_id')
      .label('Transfer id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.DATE)
      .name('fecha')
      .label('Fecha')
      .defaultValue('Today')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  let session = StorageUtils.getItem('session');
  let warehouseUrl = `${Constants.API_BASE_URL}branches/${session['branch_id']}/warehouses`;
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('almacen_origen')
      .label('Almacen origen')
      .url(warehouseUrl)
      .valueField('almacen_id')
      .textField('descripcion')
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('origen')
      .label('Almacen origen')
      .formatter((cell: ColumnCellTemplateData) => cell.data.almacen_origen)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('almacen_destino')
      .label('Almacen destino')
      .url(warehouseUrl)
      .valueField('almacen_id')
      .textField('descripcion')
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('destino')
      .label('Almacen destino')
      .formatter((cell: ColumnCellTemplateData) => cell.data.almacen_destino)
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXTAREA).width(100).name('nota').label('Nota').build(),
  );

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
