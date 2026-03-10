import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { SharedUtils } from '@shared/lib/SharedUtils';

export const CashRegisterShiftsFields = () => {
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
      .type(FieldType.ENUM)
      .name('status')
      .label('Estado')
      .data([
        { value: 'OPEN', text: 'Abierto' },
        { value: 'CLOSED', text: 'Cerrado' },
      ])
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === 'OPEN') {
          return <span className="badge badge-outlined rounded-pill text-bg-success">Abierto</span>;
        }
        if (cell.value === 'CLOSED') {
          return (
            <span className="badge badge-outlined rounded-pill text-bg-secondary">Cerrado</span>
          );
        }
      })
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('openingAmount')
      .label('Saldo inicial')
      .align('right')
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('expectedAmount')
      .label('Monto esperado')
      .align('right')
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('countedAmount')
      .label('Monto contado')
      .align('right')
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('differenceAmount')
      .label('Diferencia')
      .align('right')
      .build(),
  );

  fields.push(new FieldBuilder().type(FieldType.TEXTAREA).name('closeNote').label('Nota').build());

  fields.push(
    new FieldBuilder()
      .type(FieldType.DATETIME)
      .name('openedAt')
      .label('Apertura')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateTimeFormatter(cell.value))
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.DATETIME)
      .name('closedAt')
      .label('Cierre')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateTimeFormatter(cell.value))
      .build(),
  );

  return fields;
};
