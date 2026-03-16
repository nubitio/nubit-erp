import { Field } from '@platform/field/Field';
import { identityField, textField } from '@platform/field/FieldBuilders';

export const RoleFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Descripcion').required(true).build());

  return fields;
};
