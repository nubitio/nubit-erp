import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';

export const VoucherTypeFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .type(FieldType.TEXT)
      .name('code')
      .label('Codigo')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('name')
      .label('Descripcion')
      .width('100%')
      .required(true)
      .build(),
  );

  return fields;
};
