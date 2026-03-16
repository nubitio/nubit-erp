import { Field } from '@platform/field/Field';
import { identityField, enumField, textField } from '@platform/field/FieldBuilders';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const OperationFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(textField().name('name').label('Descripcion').width('100%').required(true).build());
  fields.push(
    enumField([
      { value: 1, text: 'Entrada' },
      { value: 2, text: 'Salida' },
    ])
      .name('type')
      .label('Tipo')
      .align('center')
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
