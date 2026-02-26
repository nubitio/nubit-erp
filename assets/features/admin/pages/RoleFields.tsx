import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';

export const RoleFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .valueType('number')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('name')
      .label('Descripcion')
      .required(true)
      .build(),
  );

  return fields;
};
