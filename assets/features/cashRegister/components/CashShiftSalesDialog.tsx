import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  AppDialog,
  Badge,
  Button,
  IconButton,
  Skeleton,
  type BadgeVariant,
} from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import { sunatStatus, type SunatStage } from '@features/sales/sunat/sunatStatus';
import { cashSessionService, type CashShiftSale } from '../services/cashSessionService';
import './CashShiftSalesDialog.scss';

interface Props {
  open: boolean;
  /** The open session: the list is scoped to it, and a different session is a different list. */
  sessionId: number;
  onClose: () => void;
  /** Prints the receipt again. Omit it where the device cannot print. */
  onReprint?: (saleId: string) => void;
}

export const CASH_SHIFT_SALES_QUERY_KEY = 'cash-shift-sales';

const money = (value: string | number) => NumberUtils.formatCurrency(Number(value));
const time = (value?: string | null) =>
  value ? new Date(value).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) : '';

const STAGE_VARIANT: Record<SunatStage, BadgeVariant> = {
  accepted: 'success',
  observed: 'warning',
  pending: 'warning',
  sent: 'info',
  rejected: 'danger',
  error: 'danger',
};

/** What the cashier reads about a sale: voided, or the state of its receipt. */
function SaleState({ sale }: { sale: CashShiftSale }) {
  if (sale.status === 'VOIDED') return <Badge variant="danger">Anulada</Badge>;
  if (!sale.documentNumber) return null;
  const state = sunatStatus(sale.sunatStatus);
  return (
    <Badge variant={STAGE_VARIANT[state.stage]} outlined>
      {state.label}
    </Badge>
  );
}

const paymentsLabel = (sale: CashShiftSale) =>
  sale.payments.length === 0
    ? '—'
    : sale.payments.length === 1
      ? sale.payments[0].method
      : sale.payments.map((payment) => `${payment.method} ${money(payment.amount)}`).join(' · ');

/**
 * The sales of the shift in progress, so a cashier whose count does not match can find the sale
 * behind the difference. Read-only: voiding stays with whoever holds that permission.
 */
export function CashShiftSalesDialog({ open, sessionId, onClose, onReprint }: Props) {
  const sales = useQuery({
    queryKey: [CASH_SHIFT_SALES_QUERY_KEY, sessionId],
    queryFn: () => cashSessionService.getShiftSales(),
    enabled: open,
    // A sale rung up a second ago must be in the list: never serve a cached one.
    staleTime: 0,
  });
  const data = sales.data;

  return (
    <AppDialog
      open={open}
      title="Ventas del turno"
      onClose={onClose}
      width={880}
      maxWidth="96vw"
      footer={
        <Button variant="secondary" onClick={onClose}>
          Cerrar
        </Button>
      }
    >
      <div className="cash-shift-sales">
        {sales.isError ? (
          <Alert tone="warning">
            No se pudieron cargar las ventas del turno.{' '}
            <Button variant="secondary" onClick={() => void sales.refetch()}>
              Reintentar
            </Button>
          </Alert>
        ) : !data ? (
          <Skeleton variant="text" lines={6} />
        ) : (
          <>
            <p className="cash-shift-sales__summary" role="status">
              <strong>{data.count}</strong> {data.count === 1 ? 'venta' : 'ventas'} ·{' '}
              <strong>{money(data.total)}</strong>
              <small>Las anuladas se listan pero no suman.</small>
            </p>
            {data.sales.length === 0 ? (
              <p className="cash-shift-sales__muted">Todavía no hay ventas en este turno.</p>
            ) : (
              <ul className="cash-shift-sales__list" data-testid="cash-shift-sales-list">
                {data.sales.map((sale) => (
                  <li key={sale.id} className="cash-shift-sales__row">
                    <span className="cash-shift-sales__time">{time(sale.issuedAt)}</span>
                    <span className="cash-shift-sales__what">
                      <strong>{sale.documentNumber ?? 'Sin comprobante'}</strong>
                      <span>{paymentsLabel(sale)}</span>
                    </span>
                    <span className="cash-shift-sales__money">
                      <span className={sale.status === 'VOIDED' ? 'cash-shift-sales__voided' : ''}>
                        {money(sale.total)}
                      </span>
                      <SaleState sale={sale} />
                    </span>
                    {onReprint && sale.status !== 'VOIDED' ? (
                      <IconButton
                        icon="ph ph-printer"
                        label="Reimprimir"
                        onClick={() => onReprint(sale.id)}
                      />
                    ) : (
                      <span aria-hidden="true" />
                    )}
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>
    </AppDialog>
  );
}
