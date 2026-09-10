import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useRef, useState } from 'react';
import customerAccountService, { type CollectOnAccountApplication } from './services/customerAccountService';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';

const API = getCoreApiBaseUrl();

type Mode = 'receive' | 'apply';

interface Props {
  customerId: number;
  customerName?: string;
  currency?: string;
  onClose: () => void;
  onDone: () => void;
}

export const CustomerAdvanceDialog = ({ customerId, customerName, currency = 'PEN', onClose, onDone }: Props) => {
  const [mode, setMode] = useState<Mode>('receive');
  const [amountValue, setAmountValue] = useState('');
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [available, setAvailable] = useState<string | null>(null);
  const [applied, setApplied] = useState<CollectOnAccountApplication[] | null>(null);

  const money = (value: string | number) => NumberUtils.formatCurrency(Number(value), currency);

  const reset = () => {
    setAmountValue('');
    setPaymentMethodId(null);
    setApplied(null);
    if (referenceRef.current) referenceRef.current.value = '';
  };

  const submit = () => {
    if (isProcessing) return;
    const amount = amountValue !== '' ? parseFloat(amountValue) : NaN;
    if (!Number.isFinite(amount) || amount <= 0) {
      toast('Ingresa un monto mayor a 0', 'error');
      return;
    }
    if (mode === 'receive' && paymentMethodId === null) {
      toast('Selecciona el método de pago', 'error');
      return;
    }

    setIsProcessing(true);
    const request =
      mode === 'receive'
        ? customerAccountService
            .receiveAdvance({
              customerId,
              amount,
              paymentMethodId: parseInt(paymentMethodId as string, 10),
              reference: referenceRef.current?.value || undefined,
            })
            .then(({ data }) => {
              toast('Anticipo recibido', 'success');
              setAvailable(data.availableAdvance);
              reset();
            })
        : customerAccountService.applyAdvance({ customerId, amount }).then(({ data }) => {
            toast(`Anticipo aplicado a ${data.applications.length} comprobante(s)`, 'success');
            setAvailable(data.availableAdvance);
            setApplied(data.applications);
          });

    request
      .catch(() => {
        /* HttpClient surfaces the API error; keep the form open. */
      })
      .finally(() => setIsProcessing(false));
  };

  return (
    <AppDialog
      open
      title="Anticipos del cliente"
      onClose={() => {
        if (!isProcessing) onClose();
      }}
      width={560}
      closeOnOutsideClick={false}
    >
      <div style={{ marginBottom: 12 }}>
        <h3 style={{ marginBottom: 4 }}>{customerName ?? 'Cliente'}</h3>
        {available !== null && (
          <p style={{ margin: 0, color: 'var(--text-muted)' }}>
            Saldo a favor disponible: <strong>{money(available)}</strong>
          </p>
        )}
      </div>

      <div className="mb-3" style={{ display: 'flex', gap: 8 }}>
        <Button
          type="button"
          variant={mode === 'receive' ? 'primary' : 'secondary'}
          onClick={() => {
            setMode('receive');
            reset();
          }}
        >
          Recibir
        </Button>
        <Button
          type="button"
          variant={mode === 'apply' ? 'primary' : 'secondary'}
          onClick={() => {
            setMode('apply');
            reset();
          }}
        >
          Aplicar a ventas
        </Button>
      </div>

      {applied ? (
        <div>
          <strong>Aplicación</strong>
          <ul style={{ marginTop: 6 }}>
            {applied.map((line) => (
              <li key={line.saleId}>
                {line.document} · {money(line.applied)} · saldo {money(line.balanceDueAfter)}
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
              htmlFor="customer-advance-amount"
            >
              <TextField
                value={amountValue}
                onChange={(event) => setAmountValue(event.target.value)}
                disabled={isProcessing}
                id="customer-advance-amount"
                type="number"
                min={0.01}
                step={0.01}
                required
                placeholder="0.00"
              />
            </FormField>
          </div>

          {mode === 'receive' && (
            <>
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
                  htmlFor="customer-advance-method"
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
                <FormField label="Referencia" htmlFor="customer-advance-reference">
                  <TextField ref={referenceRef} id="customer-advance-reference" placeholder="N° de operación" />
                </FormField>
              </div>
            </>
          )}

          {mode === 'apply' && (
            <p style={{ margin: '0 0 12px', color: 'var(--text-muted)' }}>
              El anticipo se aplica a las facturas pendientes por antigüedad (FIFO). No puede exceder el saldo a favor
              disponible.
            </p>
          )}

          <Button
            type="submit"
            variant="success"
            fullWidth
            loading={isProcessing}
            data-testid="customer-advance-submit-btn"
          >
            {isProcessing ? 'Procesando...' : mode === 'receive' ? 'Registrar anticipo' : 'Aplicar anticipo'}
          </Button>
        </form>
      )}
    </AppDialog>
  );
};
