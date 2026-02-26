import { Constants } from '@platform/common/Constants';
import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const CashRegisterFields = () => {
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
      .name('branch')
      .label('Sucursal')
      .url(Constants.API_BASE_URL + 'branches')
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .width('100%')
      .name('name')
      .label('Nombre')
      .required(true)
      .build(),
  );

  return fields;
};
