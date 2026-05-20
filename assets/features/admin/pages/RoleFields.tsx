import { identityField, textField, type Field } from '@nubit/core';

export const RoleFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Descripcion').required(true).build());

  return fields;
};
