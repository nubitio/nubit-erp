import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { Constants } from '../../core/common/Constants';
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
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('name')
      .label('Descripcion')
      .sortable(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('parent')
      .label('Categoria padre')
      .url(Constants.API_BASE_URL + 'categories')
      .valueField('categoria_id')
      .textField('descripcion')
      .formatter((cell: ColumnCellTemplateData) => cell.data.parent?.descripcion)
      .build(),
  );

  return fields;
};
