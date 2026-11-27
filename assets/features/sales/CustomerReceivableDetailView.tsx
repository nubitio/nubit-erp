import { useEffect, useMemo, useState } from 'react';
import { Alert, Badge, Button, ButtonLink, ContextMenu, EmptyState, Text } from '@nubitio/ui';
import {
  RECEIVABLE_STATUS_LABEL,
  formatDate,
  formatInvoiceNumber,
  formatMoney,
  receivableStatus,
  type ReceivableStatusKey,
} from '@shared/lib/displayFormat';
import { hasPermission } from '@shared/permissions';
import { listOpenInvoices, type ReceivableInvoice } from './services/receivableService';
import { CollectionReminderDialog } from './reminders/CollectionReminderDialog';
import './CustomerReceivableDetailView.scss';

export interface ReceivableCustomer {
  id: number;
  name: string;
  totalBalance: string;
  currency: string;
}

interface Props {
  branchId?: string;
  customer: ReceivableCustomer;
  onBack: () => void;
  onCollect: () => void;
  onAdvance: () => void;
  onDownloadStatement: () => void;
}

const STATUS_TONE: Record<ReceivableStatusKey, 'success' | 'warning' | 'danger' | 'secondary'> = {
  paid: 'success',
  pending: 'warning',
  partial: 'secondary',
  overdue: 'danger',
};

interface FlatPayment {
  key: string;
  paidAt: string | null;
  methodName: string | null;
  amount: string;
  currency: string;
  reference: string | null;
}

function OpenInvoices({
  invoices,
  canRemind,
  onRemind,
}: {
  invoices: ReceivableInvoice[];
  canRemind: boolean;
  onRemind: (invoice: ReceivableInvoice) => void;
}) {
  if (invoices.length === 0) {
    return (
      <EmptyState
        size="sm"
        icon="hand-coins"
        title="Sin saldos pendientes"
        description="Este cliente ya no tiene comprobantes por cobrar."
      />
    );
  }

  return (
    <ul className="receivable-detail__invoices">
      {invoices.map((invoice) => {
        const status = receivableStatus(invoice);
        return (
          <li key={invoice.id} className="receivable-detail__invoice">
            <div className="receivable-detail__invoice-heading">
              <div>
                <span className="receivable-detail__invoice-number">
                  {formatInvoiceNumber(invoice.invoiceFullNumber) || 'Saldo inicial'}
                </span>
                <span className="receivable-detail__invoice-date">
                  Vence {formatDate(invoice.dueDate) || '—'}
                </span>
              </div>
              <Badge variant={STATUS_TONE[status]} outlined>
                {RECEIVABLE_STATUS_LABEL[status]}
              </Badge>
            </div>
            <div className="receivable-detail__invoice-amounts">
              <span>
                <small>Total</small>
                {formatMoney(invoice.grandTotal, invoice.currency)}
              </span>
              <span>
                <small>Pagado</small>
                {formatMoney(invoice.paidTotal, invoice.currency)}
              </span>
              <strong>
                <small>Saldo pendiente</small>
                {formatMoney(invoice.balanceDue, invoice.currency)}
              </strong>
            </div>
            {canRemind && status !== 'paid' && Number(invoice.balanceDue) > 0 && (
              <div className="receivable-detail__invoice-action">
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => onRemind(invoice)}
                  data-testid="receivable-remind"
                >
                  Enviar recordatorio
                </Button>
              </div>
            )}
          </li>
        );
      })}
    </ul>
  );
}

export const CustomerReceivableDetailView = ({
  customer,
  branchId,
  onBack,
  onCollect,
  onAdvance,
  onDownloadStatement,
}: Props) => {
  const [invoices, setInvoices] = useState<ReceivableInvoice[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [reminding, setReminding] = useState<ReceivableInvoice | null>(null);
  const canCollect = !branchId && hasPermission('sales.collect');
  const canRemind = !branchId && hasPermission('receivables.remind');

  useEffect(() => {
    const controller = new AbortController();
    listOpenInvoices(customer.id, controller.signal, branchId)
      .then((rows) => {
        if (!controller.signal.aborted) setInvoices(rows);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => controller.abort();
  }, [customer.id, attempt, branchId]);

  const payments = useMemo<FlatPayment[]>(() => {
    return invoices
      .flatMap((invoice) =>
        invoice.payments.map((payment, index) => ({
          key: `${invoice.id}-${index}`,
          paidAt: payment.paid_at ?? null,
          methodName: payment.method_name ?? null,
          amount: String(payment.amount ?? '0'),
          currency: invoice.currency,
          reference: payment.reference ?? null,
        })),
      )
      .sort((a, b) => String(b.paidAt ?? '').localeCompare(String(a.paidAt ?? '')))
      .slice(0, 10);
  }, [invoices]);

  return (
    <section className="receivable-detail" aria-labelledby="receivable-detail-title">
      <div className="receivable-detail__toolbar">
        <ButtonLink
          as="button"
          type="button"
          size="sm"
          variant="ghost"
          icon="ph ph-arrow-left"
          className="receivable-detail__back"
          onClick={onBack}
          data-testid="receivable-back"
        >
          Cuentas por cobrar
        </ButtonLink>
        <span className="receivable-detail__context">Detalle del cliente</span>
      </div>

      <header className="receivable-detail__header">
        <div className="receivable-detail__identity">
          <h1 id="receivable-detail-title">{customer.name}</h1>
          <p>
            {isLoading
              ? 'Cargando comprobantes…'
              : error
                ? 'No se pudo cargar el detalle de comprobantes'
                : `${invoices.length} comprobante${invoices.length === 1 ? '' : 's'} pendiente${invoices.length === 1 ? '' : 's'}`}
          </p>
        </div>
        <div className="receivable-detail__actions">
          {canCollect && (
            <Button variant="primary" onClick={onCollect} data-testid="receivable-register-payment">
              Registrar pago
            </Button>
          )}
          <ContextMenu
            trigger={
              <Button variant="secondary" className="receivable-detail__more-trigger">
                Más acciones
                <i className="ph ph-caret-down" aria-hidden="true" />
              </Button>
            }
            triggerLabel="Más acciones"
            items={[
              {
                label: 'Descargar estado de cuenta',
                icon: 'ph ph-download-simple',
                onClick: onDownloadStatement,
              },
              ...(canCollect
                ? [
                    {
                      label: 'Registrar anticipo',
                      icon: 'ph ph-hand-coins',
                      onClick: onAdvance,
                    },
                  ]
                : []),
            ]}
          />
        </div>
      </header>

      <div className="receivable-detail__layout">
        <section className="receivable-detail__panel" aria-labelledby="receivable-invoices-title">
          <div className="receivable-detail__panel-heading">
            <div>
              <h2 id="receivable-invoices-title">Comprobantes pendientes</h2>
              <p>Revisa vencimientos y saldos antes de registrar un pago.</p>
            </div>
            {!isLoading && !error && (
              <span className="receivable-detail__count">{invoices.length}</span>
            )}
          </div>

          {error ? (
            <Alert tone="danger">
              No se pudieron cargar los comprobantes.{' '}
              <Button
                variant="secondary"
                size="sm"
                onClick={() => {
                  setIsLoading(true);
                  setError(false);
                  setAttempt((value) => value + 1);
                }}
              >
                Reintentar
              </Button>
            </Alert>
          ) : isLoading ? (
            <Text tone="muted" as="p">
              Cargando comprobantes…
            </Text>
          ) : (
            <OpenInvoices invoices={invoices} canRemind={canRemind} onRemind={setReminding} />
          )}
        </section>

        <aside className="receivable-detail__side">
          <section className="receivable-detail__balance-card">
            <span>Saldo total pendiente</span>
            <strong>{formatMoney(customer.totalBalance, customer.currency)}</strong>
            <small>
              {isLoading
                ? 'Actualizando comprobantes'
                : `${invoices.length} documento${invoices.length === 1 ? '' : 's'} abiertos`}
            </small>
          </section>

          <section className="receivable-detail__panel" aria-labelledby="receivable-payments-title">
            <div className="receivable-detail__panel-heading">
              <div>
                <h2 id="receivable-payments-title">Pagos recientes</h2>
                <p>Últimos abonos registrados</p>
              </div>
            </div>
            {error ? (
              <Text tone="muted" as="p">
                Historial no disponible.
              </Text>
            ) : isLoading ? (
              <Text tone="muted" as="p">
                Cargando pagos…
              </Text>
            ) : payments.length === 0 ? (
              <p className="receivable-detail__empty-payments">Todavía no hay pagos registrados.</p>
            ) : (
              <ul className="receivable-detail__payments">
                {payments.map((payment) => (
                  <li key={payment.key}>
                    <div>
                      <strong>{payment.methodName ?? 'Pago'}</strong>
                      <span>
                        {formatDate(payment.paidAt)}
                        {payment.reference ? ` · ${payment.reference}` : ''}
                      </span>
                    </div>
                    <strong>{formatMoney(payment.amount, payment.currency)}</strong>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </aside>
      </div>

      {reminding && (
        <CollectionReminderDialog
          saleId={reminding.id}
          documentNumber={reminding.invoiceFullNumber}
          balanceDue={reminding.balanceDue}
          currency={reminding.currency}
          customerName={customer.name}
          onClose={() => setReminding(null)}
        />
      )}
    </section>
  );
};
