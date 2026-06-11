import { identityField, textField, type Field } from '@nubitio/crud';

export const RoleFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Descripcion').required(true).build());

  return fields;
};
