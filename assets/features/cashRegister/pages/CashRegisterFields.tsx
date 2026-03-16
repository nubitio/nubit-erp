import { Constants } from '@platform/common/Constants';
import { Field } from '@platform/field/Field';
import { identityField, entityField, textField } from '@platform/field/FieldBuilders';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const CashRegisterFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().width('100%').name('name').label('Nombre').required(true).build());

  return fields;
};
