import { Alert, AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { useEffect, useMemo, useState } from 'react';
import collectionService, { type CollectionResult } from './services/collectionService';
import { listOpenInvoices, type ReceivableInvoice } from './services/receivableService';
import { CollectionTenderRows, type TenderRow } from './CollectionTenderRows';
import { CollectionDistribution, centsOf, fromCents } from './CollectionDistribution';
import {
  manualPlan,
  planByDueDate,
  tenderDifference,
  toCents,
  validateManual,
  type OpenInvoice,
} from './lib/collectionPlan';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { useIdempotencyKey } from '@shared/lib/idempotency';

interface Props {
  customerId: number;
  customerName?: string;
  currency?: string;
  balanceDue?: string;
  onClose: () => void;
  onDone: () => void;
}

function initialAmount(balanceDue?: string): string {
  const balance = Number(balanceDue ?? 0);
  return Number.isFinite(balance) && balance > 0 ? balance.toFixed(2) : '';
}

const FIRST_TENDER: TenderRow = {
  key: 1,
  methodId: null,
  accountId: '',
  amount: '',
  reference: '',
};

/**
 * The collection flow of Cuentas por cobrar: the money received (in one or several ways) and where it goes.
 * The distribution is automatic, oldest due date first, until the user edits it; whatever the invoices cannot
 * absorb stays as the customer's advance. The server decides and answers with the receipt shown at the end.
 */
export const CollectPaymentDialog = ({
  customerId,
  customerName,
  currency = 'PEN',
  balanceDue,
  onClose,
  onDone,
}: Props) => {
  const [amountValue, setAmountValue] = useState(() => initialAmount(balanceDue));
  const [tenders, setTenders] = useState<TenderRow[]>([FIRST_TENDER]);
  const [invoices, setInvoices] = useState<ReceivableInvoice[]>([]);
  const [manual, setManual] = useState<Record<string, string> | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [receipt, setReceipt] = useState<CollectionResult | null>(null);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    listOpenInvoices(customerId, controller.signal)
      .then((rows) => {
        if (!controller.signal.aborted)
          setInvoices(rows.filter((row) => row.currency === currency));
      })
      .catch(() => {
        if (!controller.signal.aborted) setLoadError(true);
      });
    return () => controller.abort();
  }, [customerId, currency]);

  const amountCents = centsOf(amountValue);
  const open = useMemo<OpenInvoice[]>(
    () =>
      invoices.map((invoice) => ({
        id: invoice.id,
        dueDate: invoice.dueDate,
        issuedDate: invoice.issuedDate,
        balanceDue: invoice.balanceDue,
      })),
    [invoices],
  );

  const manualLines = useMemo(
    () =>
      Object.entries(manual ?? {}).map(([invoiceId, value]) => ({
        invoiceId,
        cents: centsOf(value),
      })),
    [manual],
  );
  const manualError = manual === null ? null : validateManual(open, amountCents, manualLines);
  const plan =
    manual === null ? planByDueDate(open, amountCents) : manualPlan(amountCents, manualLines);

  const several = tenders.length > 1;
  const tenderCents = several ? tenders.map((t) => centsOf(t.amount)) : [amountCents];
  const difference = several ? tenderDifference(amountCents, tenderCents) : 0;

  // One key per distinct request: a retry after a timeout cannot collect twice.
  const idempotency = useIdempotencyKey(JSON.stringify([customerId, amountValue, tenders, manual]));

  const startManual = () =>
    setManual(
      Object.fromEntries(plan.lines.map((line) => [line.invoiceId, fromCents(line.cents)])),
    );

  const submit = () => {
    if (isProcessing) return;
    if (amountCents <= 0) {
      toast('Ingresa un monto mayor a 0', 'error');
      return;
    }
    if (tenders.some((t) => t.methodId === null)) {
      toast('Selecciona el medio de pago', 'error');
      return;
    }
    if (difference !== 0) {
      toast('Los importes de los medios deben sumar el monto recibido', 'error');
      return;
    }
    if (manualError) {
      toast(manualError, 'error');
      return;
    }

    setIsProcessing(true);
    collectionService
      .register({
        customerId,
        currency,
        idempotencyKey: idempotency.current(),
        tenders: tenders.map((t, index) => ({
          paymentMethodId: parseInt(String(t.methodId), 10),
          amount: fromCents(tenderCents[index]),
          moneyAccountId: t.accountId,
          reference: t.reference.trim(),
        })),
        allocations:
          manual === null
            ? []
            : plan.lines.map((line) => ({ saleId: line.invoiceId, amount: fromCents(line.cents) })),
      })
      .then(({ data }) => {
        idempotency.renew();
        toast(
          `Cobranza registrada: ${NumberUtils.formatCurrency(Number(data.total), currency)}`,
          'success',
        );
        setReceipt(data);
      })
      .catch(() => {
        /* HttpClient surfaces the API error; keep the form open for correction. */
      })
      .finally(() => setIsProcessing(false));
  };

  return (
    <AppDialog
      open
      title="Registrar pago"
      onClose={() => {
        if (!isProcessing) onClose();
      }}
      width={620}
      closeOnOutsideClick={false}
      footer={
        receipt ? (
          <Button type="button" variant="primary" onClick={onDone}>
            Listo
          </Button>
        ) : (
          <>
            <Button type="button" variant="secondary" onClick={onClose} disabled={isProcessing}>
              Cancelar
            </Button>
            <Button
              type="submit"
              form="receivable-payment-form"
              variant="primary"
              loading={isProcessing}
              data-testid="receivable-payment-submit-btn"
            >
              {isProcessing ? 'Procesando...' : 'Registrar pago'}
            </Button>
          </>
        )
      }
    >
      <div className="mb-3">
        <h3 style={{ marginBottom: 4 }}>{customerName ?? 'Cliente'}</h3>
        <p style={{ margin: 0, color: 'var(--text-muted)' }}>
          {balanceDue
            ? `Saldo pendiente: ${NumberUtils.formatCurrency(Number(balanceDue), currency)}. `
            : ''}
          El pago se aplica primero a los comprobantes que vencen antes y se registra una sola vez.
        </p>
      </div>

      {receipt ? (
        <div data-testid="receivable-receipt">
          <strong>Aplicación</strong>
          <ul style={{ marginTop: 6 }}>
            {receipt.allocations.map((line) => (
              <li key={line.installmentId}>
                {line.document} · {NumberUtils.formatCurrency(Number(line.amount), currency)} ·
                saldo {NumberUtils.formatCurrency(Number(line.balanceDueAfter), currency)}
              </li>
            ))}
          </ul>
          {toCents(receipt.unapplied) > 0 && (
            <Alert tone="info">
              {NumberUtils.formatCurrency(Number(receipt.unapplied), currency)} quedaron como saldo
              a favor del cliente.
            </Alert>
          )}
        </div>
      ) : (
        <form
          id="receivable-payment-form"
          style={{ width: '100%' }}
          onSubmit={(event) => {
            event.preventDefault();
            submit();
          }}
        >
          <div className="mb-3">
            <FormField label="Monto" required htmlFor="receivable-payment-amount">
              <TextField
                value={amountValue}
                onChange={(event) => setAmountValue(event.target.value)}
                disabled={isProcessing}
                id="receivable-payment-amount"
                type="number"
                min={0.01}
                step={0.01}
                required
                placeholder="0.00"
              />
            </FormField>
          </div>

          <CollectionTenderRows
            rows={tenders}
            currency={currency}
            disabled={isProcessing}
            onChange={setTenders}
          />
          {several && difference !== 0 && (
            <Alert tone="warning">
              {difference > 0
                ? `Faltan ${NumberUtils.formatCurrency(difference / 100, currency)} por asignar a un medio.`
                : `Los medios superan el monto en ${NumberUtils.formatCurrency(-difference / 100, currency)}.`}
            </Alert>
          )}

          {loadError ? (
            <Alert tone="warning">
              No se pudieron cargar los comprobantes; el servidor repartirá el pago por vencimiento.
            </Alert>
          ) : (
            <CollectionDistribution
              invoices={invoices}
              currency={currency}
              plan={plan}
              manual={manual}
              disabled={isProcessing}
              error={manualError}
              onEdit={startManual}
              onReset={() => setManual(null)}
              onManualChange={(invoiceId, value) =>
                setManual((current) => ({ ...(current ?? {}), [invoiceId]: value }))
              }
            />
          )}
        </form>
      )}
    </AppDialog>
  );
};
