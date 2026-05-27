import { identityField, currencyField, textField, type Field, type GridCellContext } from '@nubit/crud';
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
          return <span className="badge badge-outlined rounded-pill text-bg-success">Abierta</span>;
        } else {
          return (
            <span className="badge badge-outlined rounded-pill text-bg-secondary">Cerrada</span>
          );
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
          return (
            <div style={{ display: 'flex', gap: '8px' }}>
              <button
                type="button"
                className="app-icon-button"
                title="Ver Movimientos"
                data-testid="cash-btn-movements"
                onClick={() => navigate(`/cash-shift/${cell.data.cashShiftId}/movements`)}
              >
                <i className="ph ph-eye" aria-hidden="true" />
              </button>
              <button
                type="button"
                className="app-icon-button app-icon-button--danger"
                title="Cerrar Caja"
                data-testid="cash-btn-close"
                onClick={() => navigate(`/cash-register/${cell.data.cashShiftId}/close`)}
              >
                <i className="ph ph-cash-register" aria-hidden="true" />
              </button>
            </div>
          );
        } else {
          return (
            <button
              type="button"
              className="app-button app-button--success app-button--sm"
              data-testid="cash-btn-open"
              onClick={() => navigate(`/cash-register/${cell.data.id}/open`)}
            >
              <i className="ph ph-cash-register" aria-hidden="true" />
              Abrir
            </button>
          );
        }
      })
      .build(),
  );

  return fields;
};

export default cashRegisterDashboardFields;
