import { Field } from '@nubitio/crud';
import {
  identityField,
  currencyField,
  datetimeField,
  enumField,
  textareaField,
} from '@nubitio/crud';
import type { GridCellContext } from '@nubitio/crud';
import { Badge } from '@nubitio/ui';
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
      .formatter((cell: GridCellContext) => {
        if (cell.value === 'OPEN') {
          return <Badge variant="success" outlined>Abierto</Badge>;
        }
        if (cell.value === 'CLOSED') {
          return <Badge variant="secondary" outlined>Cerrado</Badge>;
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
      .formatter((cell: GridCellContext) => SharedUtils.dateTimeFormatter(cell.value as string))
      .build(),
  );

  fields.push(
    datetimeField()
      .name('closedAt')
      .defaultValue(undefined)
      .label('Cierre')
      .formatter((cell: GridCellContext) => SharedUtils.dateTimeFormatter(cell.value as string))
      .build(),
  );

  return fields;
};
