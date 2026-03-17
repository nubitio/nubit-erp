import { Field } from '@core/field/Field';
import {
  identityField,
  entityField,
  textField,
  passwordField,
} from '@core/field/FieldBuilders';
import { Constants } from '@core/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const UserFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('username').label('Username').required(true).build());
  fields.push(
    passwordField()
      .name('plainPassword')
      .label('Contraseña')
      .helpText('Deje en blanco si no desea cambiar la contraseña')
      .visible(false)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'roles', 'id', 'name')
      .name('role')
      .label('Rol')
      .valueType('number')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .build(),
  );

  return fields;
};
