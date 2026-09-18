import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useRef, useState } from 'react';
import customerAccountService, { type CollectOnAccountApplication } from './services/customerAccountService';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';

const API = getCoreApiBaseUrl();

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

/**
 * Single collection flow for Cuentas por cobrar: registers the money once in
 * cash/bank and applies it to the customer's oldest open invoices (FIFO). The
 * per-invoice and advance flows were folded into this one for SMB use.
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
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [applied, setApplied] = useState<CollectOnAccountApplication[] | null>(null);

  const submit = () => {
    if (isProcessing) return;
    const amount = amountValue !== '' ? parseFloat(amountValue) : NaN;
    if (!Number.isFinite(amount) || amount <= 0 || paymentMethodId === null) {
      toast('Completa el monto y el método de pago', 'error');
      return;
    }

    setIsProcessing(true);
    customerAccountService
      .collectOnAccount({
        customerId,
        amount,
        paymentMethodId: parseInt(paymentMethodId, 10),
        reference: referenceRef.current?.value || undefined,
      })
      .then(({ data }) => {
        toast(`Pago aplicado a ${data.applications.length} comprobante(s)`, 'success');
        setApplied(data.applications);
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
      width={560}
      closeOnOutsideClick={false}
    >
      <div className="mb-3">
        <h3 style={{ marginBottom: 4 }}>{customerName ?? 'Cliente'}</h3>
        <p style={{ margin: 0, color: 'var(--text-muted)' }}>
          {balanceDue
            ? `Saldo pendiente: ${NumberUtils.formatCurrency(Number(balanceDue), currency)}. `
            : ''}
          El pago se aplica a los comprobantes más antiguos y se registra una sola vez en caja o banco.
        </p>
      </div>

      {applied ? (
        <div>
          <strong>Aplicación</strong>
          <ul style={{ marginTop: 6 }}>
            {applied.map((line) => (
              <li key={line.saleId}>
                {line.document} · {NumberUtils.formatCurrency(Number(line.applied), currency)} · saldo{' '}
                {NumberUtils.formatCurrency(Number(line.balanceDueAfter), currency)}
              </li>
            ))}
          </ul>
          <Button type="button" variant="success" fullWidth onClick={onDone}>
            Listo
          </Button>
        </div>
      ) : (
        <form
          style={{ width: '100%' }}
          onSubmit={(e) => {
            e.preventDefault();
            submit();
          }}
        >
          <div className="mb-3">
            <FormField
              label={
                <>
                  Monto{' '}
                  <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>
                    *
                  </span>
                </>
              }
              htmlFor="receivable-payment-amount"
            >
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

          <div className="mb-3">
            <FormField
              label={
                <>
                  Método de pago{' '}
                  <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>
                    *
                  </span>
                </>
              }
              htmlFor="receivable-payment-method"
            >
              <EntityDropdown
                url={API + 'payment-methods'}
                filters={[
                  { field: 'settlementBehavior', operator: '=', value: 'COLLECTED' },
                  { field: 'isActive', operator: '=', value: 1 },
                ]}
                value={paymentMethodId}
                onChange={setPaymentMethodId}
                placeholder="Selecciona un método de pago"
                searchExpr={['name']}
              />
            </FormField>
          </div>

          <div className="mb-3">
            <FormField label="Referencia" htmlFor="receivable-payment-reference">
              <TextField ref={referenceRef} id="receivable-payment-reference" placeholder="N° de operación" />
            </FormField>
          </div>

          <Button
            type="submit"
            variant="success"
            fullWidth
            loading={isProcessing}
            data-testid="receivable-payment-submit-btn"
          >
            {isProcessing ? 'Procesando...' : 'Registrar pago'}
          </Button>
        </form>
      )}
    </AppDialog>
  );
};
