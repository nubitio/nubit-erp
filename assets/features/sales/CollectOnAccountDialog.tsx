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
  onClose: () => void;
  onDone: () => void;
}

export const CollectOnAccountDialog = ({ customerId, customerName, currency = 'PEN', onClose, onDone }: Props) => {
  const [amountValue, setAmountValue] = useState('');
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
        toast(`Cobro aplicado a ${data.applications.length} comprobante(s)`, 'success');
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
      title="Cobro a cuenta del cliente"
      onClose={() => {
        if (!isProcessing) onClose();
      }}
      width={560}
      closeOnOutsideClick={false}
    >
      <div style={{ marginBottom: 16 }}>
        <h3 style={{ marginBottom: 4 }}>{customerName ?? 'Cliente'}</h3>
        <p style={{ margin: 0, color: 'var(--text-muted)' }}>
          El monto se registra una vez en caja/banco y se aplica a las facturas pendientes por antigüedad (FIFO).
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
              htmlFor="collect-on-account-amount"
            >
              <TextField
                value={amountValue}
                onChange={(event) => setAmountValue(event.target.value)}
                disabled={isProcessing}
                id="collect-on-account-amount"
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
              htmlFor="collect-on-account-method"
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
            <FormField label="Referencia" htmlFor="collect-on-account-reference">
              <TextField ref={referenceRef} id="collect-on-account-reference" placeholder="N° de operación" />
            </FormField>
          </div>

          <Button
            type="submit"
            variant="success"
            fullWidth
            loading={isProcessing}
            data-testid="collect-on-account-submit-btn"
          >
            {isProcessing ? 'Procesando...' : 'Registrar cobro a cuenta'}
          </Button>
        </form>
      )}
    </AppDialog>
  );
};
