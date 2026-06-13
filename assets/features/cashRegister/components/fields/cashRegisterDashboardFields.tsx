import { identityField, currencyField, textField, type Field, type GridCellContext } from '@nubitio/crud';
import { Badge, Button, IconButton } from '@nubitio/ui';
import NumberUtils from '../../../../shared/lib/NumberUtils';

const cashRegisterDashboardFields = (navigate: (path: string) => void) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(textField().name('name').label('Caja').build());
  fields.push(
    textField()
      .name('status')
      .label('Estado')
      .formatter((cell: GridCellContext) => {
        if (cell.data.status === 'open') {
          return <Badge variant="success" outlined>Abierta</Badge>;
        } else {
          return <Badge variant="secondary" outlined>Cerrada</Badge>;
        }
      })
      .build(),
  );
  fields.push(textField().name('cashier').label('Cajero').build());
  fields.push(
    currencyField()
      .name('todaySales')
      .label('Ventas hoy')
      .formatter((cell: GridCellContext) =>
        cell.value != null ? NumberUtils.formatCurrency(Number(cell.value)) : '—',
      )
      .build(),
  );
  fields.push(
    currencyField()
      .name('balance')
      .label('Saldo actual')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number))
      .build(),
  );
  fields.push(
    textField()
      .name('-')
      .label('')
      .align('center')
      .filterable(false)
      .formatter((cell: GridCellContext) => {
        if (cell.data.status === 'open') {
          const cashSessionId = cell.data.cashSessionId ?? cell.data.cashShiftId;

          return (
            <div style={{ display: 'flex', gap: '8px' }}>
              <IconButton
                icon="ph ph-eye"
                label="Ver Movimientos"
                data-testid="cash-btn-movements"
                onClick={() => navigate(`/cash-session/${cashSessionId}/movements`)}
              />
              <IconButton
                icon="ph ph-cash-register"
                label="Cerrar Caja"
                variant="danger"
                data-testid="cash-btn-close"
                onClick={() => navigate(`/cash-session/${cashSessionId}/close`)}
              />
            </div>
          );
        } else {
          return (
            <Button
              variant="success"
              size="sm"
              icon="ph ph-cash-register"
              data-testid="cash-btn-open"
              onClick={() => navigate(`/cash-register/${cell.data.id}/open`)}
            >
              Abrir
            </Button>
          );
        }
      })
      .build(),
  );

  return fields;
};

export default cashRegisterDashboardFields;
