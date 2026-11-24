import { Badge, Button } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import type { CashRegisterDashboardDto } from '../services/cashboxService';
import { closureText, formatDate, formatDateTime, formatTime, shiftCode } from '../model/format';

const initials = (name: string) => name.slice(0, 2).toUpperCase();

interface Props {
  register: CashRegisterDashboardDto;
  /** The shift has been open for more than 24 hours: it needs counting before anything else. */
  stale: boolean;
  onNavigate: (path: string) => void;
}

/** One register: who has it, what it holds and the one action that makes sense now. */
export function RegisterCard({ register, stale, onNavigate }: Props) {
  const isOpen = register.status === 'open';
  const sessionId = register.cashSessionId ?? register.cashShiftId;

  return (
    <article className="cashbox-card" data-testid="cashbox-card" data-status={register.status}>
      <header className="cashbox-card__header">
        <h3 className="cashbox-card__name">{register.name}</h3>
        {isOpen ? (
          <Badge variant={stale ? 'warning' : 'success'} pill outlined>
            {stale ? 'Arqueo pendiente' : 'Abierta'}
          </Badge>
        ) : (
          <Badge variant="secondary" pill outlined>
            Cerrada
          </Badge>
        )}
      </header>

      {isOpen ? (
        <>
          <div className="cashbox-card__cashier">
            <span className="cashbox-card__avatar" aria-hidden="true">
              {initials(register.cashier ?? '—')}
            </span>
            {register.cashier ?? 'Sin cajero'}
          </div>
          <dl className="cashbox-card__figures">
            <div>
              <dt>Efectivo esperado</dt>
              <dd>{NumberUtils.formatCurrency(register.balance)}</dd>
            </div>
            <div>
              <dt>Ventas del turno</dt>
              <dd>{NumberUtils.formatCurrency(Number(register.todaySales))}</dd>
            </div>
          </dl>
          <footer className="cashbox-card__footer">
            <span className="cashbox-card__meta">
              {stale && register.openedAt
                ? `${shiftCode(sessionId ?? 0)} abierto desde ${formatDateTime(register.openedAt)}`
                : `Turno ${shiftCode(sessionId ?? 0)}${
                    register.openedAt ? ` · desde ${formatTime(register.openedAt)}` : ''
                  }`}
            </span>
            {stale ? (
              <Button
                variant="danger"
                data-testid="cash-btn-close"
                onClick={() => onNavigate(`/cash-session/${sessionId}/close`)}
              >
                Hacer arqueo
              </Button>
            ) : (
              <Button
                variant="secondary"
                data-testid="cash-btn-movements"
                onClick={() => onNavigate(`/cash-session/${sessionId}/movements`)}
              >
                Ver turno
              </Button>
            )}
          </footer>
        </>
      ) : (
        <>
          <p className="cashbox-card__idle">Sin cajero asignado</p>
          {register.lastClosedAt && (
            <div className="cashbox-card__last">
              <div className="cashbox-card__last-label">Último cierre</div>
              <div>
                {formatDate(register.lastClosedAt)} {formatTime(register.lastClosedAt)} ·{' '}
                {closureText(register.lastDifference)}
              </div>
            </div>
          )}
          <footer className="cashbox-card__footer">
            <span className="cashbox-card__meta">Disponible para abrir</span>
            <Button
              variant="secondary"
              data-testid="cash-btn-open"
              onClick={() => onNavigate(`/cash-register/${register.id}/open`)}
            >
              Abrir turno
            </Button>
          </footer>
        </>
      )}
    </article>
  );
}
