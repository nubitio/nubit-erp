import { useEffect, useMemo, useState } from 'react';
import { Alert, Badge, Button, Drawer, EmptyState, Text } from '@nubitio/ui';
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
import './CustomerReceivableDrawer.scss';

export interface ReceivableCustomer {
  id: number;
  name: string;
  totalBalance: string;
  currency: string;
}

interface Props {
  customer: ReceivableCustomer | null;
  refreshKey?: number;
  onClose: () => void;
  onCollect: (customer: ReceivableCustomer) => void;
  onAdvance: (customer: ReceivableCustomer) => void;
  onDownloadStatement: (customerId: number) => void;
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

function CustomerReceivableDetail({ customer }: { customer: ReceivableCustomer }) {
  const [invoices, setInvoices] = useState<ReceivableInvoice[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(false);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    listOpenInvoices(customer.id, controller.signal)
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
  }, [customer.id, attempt]);

  const payments = useMemo<FlatPayment[]>(() => {
    const flat = invoices.flatMap((invoice) =>
      invoice.payments.map((payment, index) => ({
        key: `${invoice.id}-${index}`,
        paidAt: payment.paid_at ?? null,
        methodName: payment.method_name ?? null,
        amount: String(payment.amount ?? '0'),
        currency: invoice.currency,
        reference: payment.reference ?? null,
      })),
    );

    return flat
      .sort((a, b) => String(b.paidAt ?? '').localeCompare(String(a.paidAt ?? '')))
      .slice(0, 10);
  }, [invoices]);

  return (
    <div className="receivable-drawer__content">
      <div className="receivable-drawer__balance">
        <Text tone="muted" as="p">
          Saldo pendiente
        </Text>
        <strong>{formatMoney(customer.totalBalance, customer.currency)}</strong>
        <Text tone="muted" as="p">
          {isLoading
            ? 'Cargando comprobantes…'
            : error
              ? 'Comprobantes no disponibles'
              : `${invoices.length} comprobante${invoices.length === 1 ? '' : 's'} por cobrar`}
        </Text>
      </div>

      <section className="receivable-drawer__section">
        <h4 className="receivable-drawer__section-title">Comprobantes</h4>
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
              Reintentar comprobantes
            </Button>
          </Alert>
        ) : isLoading ? (
          <Text tone="muted" as="p">
            Cargando comprobantes...
          </Text>
        ) : invoices.length === 0 ? (
          <EmptyState
            size="sm"
            icon="hand-coins"
            title="Sin saldos pendientes"
            description="Este cliente ya no tiene comprobantes por cobrar."
          />
        ) : (
          <ul className="receivable-drawer__invoices">
            {invoices.map((invoice) => {
              const status = receivableStatus(invoice);
              return (
                <li key={invoice.id} className="receivable-drawer__invoice">
                  <div className="receivable-drawer__invoice-top">
                    <span className="receivable-drawer__invoice-doc">
                      {formatInvoiceNumber(invoice.invoiceFullNumber) || 'Saldo inicial'}
                    </span>
                    <Badge variant={STATUS_TONE[status]} outlined>
                      {RECEIVABLE_STATUS_LABEL[status]}
                    </Badge>
                  </div>
                  <div className="receivable-drawer__invoice-meta">
                    <span>Vence {formatDate(invoice.dueDate) || '—'}</span>
                    <span>Total {formatMoney(invoice.grandTotal, invoice.currency)}</span>
                    <span>Pagado {formatMoney(invoice.paidTotal, invoice.currency)}</span>
                  </div>
                  <div className="receivable-drawer__invoice-balance">
                    Saldo {formatMoney(invoice.balanceDue, invoice.currency)}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      {!isLoading && !error && payments.length > 0 && (
        <section className="receivable-drawer__section">
          <h4 className="receivable-drawer__section-title">Abonos recientes</h4>
          <ul className="receivable-drawer__payments">
            {payments.map((payment) => (
              <li key={payment.key}>
                <span>
                  {formatDate(payment.paidAt)} · {payment.methodName ?? 'Pago'}
                  {payment.reference ? ` · ${payment.reference}` : ''}
                </span>
                <span>{formatMoney(payment.amount, payment.currency)}</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

/**
 * Read-only detail for one debtor: open invoices, recent payments and the
 * actions that used to live in the now-removed "Comprobantes" tab.
 */
export const CustomerReceivableDrawer = ({
  customer,
  refreshKey = 0,
  onClose,
  onCollect,
  onAdvance,
  onDownloadStatement,
}: Props) => {
  const canCollect = hasPermission('sales.collect');

  return (
    <Drawer
      isOpen={customer !== null}
      onClose={onClose}
      title={customer?.name ?? 'Cliente'}
      width={560}
      scrim="subtle"
      className="receivable-drawer"
      aria-label="Detalle de cuenta por cobrar"
      footer={
        customer ? (
          <div className="receivable-drawer__footer">
            {canCollect && (
              <Button
                variant="primary"
                fullWidth
                onClick={() => onCollect(customer)}
                data-testid="receivable-register-payment"
              >
                Registrar pago
              </Button>
            )}
            <div className="receivable-drawer__footer-secondary">
              <Button variant="secondary" onClick={() => onDownloadStatement(customer.id)}>
                Estado de cuenta
              </Button>
              {canCollect && (
                <Button variant="ghost" onClick={() => onAdvance(customer)}>
                  Anticipo
                </Button>
              )}
            </div>
          </div>
        ) : null
      }
    >
      {customer && (
        <CustomerReceivableDetail key={`${customer.id}-${refreshKey}`} customer={customer} />
      )}
    </Drawer>
  );
};
