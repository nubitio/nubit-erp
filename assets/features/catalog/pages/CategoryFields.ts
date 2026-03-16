import { Constants } from '@platform/common/Constants';
import { Field } from '@platform/field/Field';
import { identityField, entityField, textField } from '@platform/field/FieldBuilders';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const CategoryFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(textField().name('name').label('Nombre').sortable(true).build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'categories', 'id', 'name')
      .name('parent')
      .label('Categoría Padre')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name || '')
      .build(),
  );

  return fields;
};
