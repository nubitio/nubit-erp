import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';

export const DepartmentFields = () => {
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
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('name').label('Name').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('description')
      .label('Description')
      .width(100)
      .build(),
  );

  return fields;
};
