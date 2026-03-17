import { Field } from '@core/field/Field';
import {
  identityField,
  currencyField,
  datetimeField,
  enumField,
  textareaField,
} from '@core/field/FieldBuilders';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { SharedUtils } from '@shared/lib/SharedUtils';

export const CashRegisterShiftsFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());

  fields.push(
    enumField([
      { value: 'OPEN', text: 'Abierto' },
      { value: 'CLOSED', text: 'Cerrado' },
    ])
      .name('status')
      .label('Estado')
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

  fields.push(currencyField().name('openingAmount').label('Saldo inicial').build());

  fields.push(currencyField().name('expectedAmount').label('Monto esperado').build());

  fields.push(currencyField().name('countedAmount').label('Monto contado').build());

  fields.push(currencyField().name('differenceAmount').label('Diferencia').build());

  fields.push(textareaField().name('closeNote').label('Nota').build());

  fields.push(
    datetimeField()
      .name('openedAt')
      .defaultValue(undefined)
      .label('Apertura')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateTimeFormatter(cell.value))
      .build(),
  );

  fields.push(
    datetimeField()
      .name('closedAt')
      .defaultValue(undefined)
      .label('Cierre')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateTimeFormatter(cell.value))
      .build(),
  );

  return fields;
};
