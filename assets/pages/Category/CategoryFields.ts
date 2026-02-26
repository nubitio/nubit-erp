import { Constants } from '@platform/common/Constants';
import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const CategoryFields = () => {
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
    new FieldBuilder().type(FieldType.TEXT).name('name').label('Nombre').sortable(true).build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('parent')
      .label('Categoría Padre')
      .url(Constants.API_BASE_URL + 'categories')
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name || '')
      .build(),
  );

  return fields;
};
