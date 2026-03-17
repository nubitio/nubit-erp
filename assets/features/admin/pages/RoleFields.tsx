import { Field } from '@core/field/Field';
import { identityField, textField } from '@core/field/FieldBuilders';

export const RoleFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Descripcion').required(true).build());

  return fields;
};
