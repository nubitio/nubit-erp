import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const OperationFields = () => {
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
      .width('100%')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('type')
      .label('Tipo')
      .align('center')
      .data([
        { value: 1, text: 'Entrada' },
        { value: 2, text: 'Salida' },
      ])
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === 1) {
          return <span className="badge badge-outlined rounded-pill text-bg-success">Entrada</span>;
        } else if (cell.value === 2) {
          return <span className="badge badge-outlined rounded-pill text-bg-danger">Salida</span>;
        }
      })
      .required(true)
      .build(),
  );

  return fields;
};
