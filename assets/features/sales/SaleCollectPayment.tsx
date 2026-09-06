import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useEffect, useRef, useState } from 'react';
import saleCollectService from './services/saleCollectService';
import toast from '@shared/lib/SharedUtils';
import { httpClient } from '@shared/api/HttpClient';
import { saleEndpoints } from './api/saleEndpoints';
import NumberUtils from '@shared/lib/NumberUtils';

const API = getCoreApiBaseUrl();

type CollectableSale = {
  customer?: { businessName?: string } | null;
  invoiceFullNumber?: string | null;
  currency?: string;
  grandTotal: string;
  paidTotal: string;
  balanceDue: string;
  payments?: Array<{ method_name?: string | null; amount?: string; paid_at?: string | null; reference?: string | null }>;
};

export const SaleCollectPayment = ({ id, onClose, onCollected }: { id: string; onClose: () => void; onCollected: () => void }) => {
  const [amountValue, setAmountValue] = useState('');
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [sale, setSale] = useState<CollectableSale | null>(null);

  useEffect(() => {
    if (!id) return;
    const controller = new AbortController();
    httpClient
      .get<CollectableSale>(saleEndpoints.detail(id), { signal: controller.signal })
      .then(({ data }) => {
        setSale(data);
        setAmountValue(data.balanceDue);
      })
      .catch(() => { if (!controller.signal.aborted) toast('No se pudo cargar el estado de cuenta', 'error'); });
    return () => controller.abort();
  }, [id]);

  const registerCollection = () => {
    if (isProcessing || !sale) return;
    const raw = amountValue;
    const amount = raw !== undefined && raw !== '' ? parseFloat(raw) : NaN;

    if (id === undefined || !Number.isFinite(amount) || amount <= 0 || amount > Number(sale.balanceDue) || paymentMethodId === null) {
      toast('Completa el monto y el método de pago', 'error');
      return;
    }

    setIsProcessing(true);
    saleCollectService()
      .collect(id, {
        amount,
        paymentMethodId: parseInt(paymentMethodId, 10),
        reference: referenceRef.current?.value || undefined,
      })
      .then(() => {
        toast('Cobro registrado', 'success');
        onCollected();
      })
      .catch(() => { /* HttpClient displays the API error; keep the form open for correction. */ })
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <AppDialog open title="Registrar cobro de venta" onClose={() => { if (!isProcessing) onClose(); }} width={640} closeOnOutsideClick={false}>
      {sale ? (
        <div style={{ marginBottom: 20, maxWidth: 640 }}>
          <h3 style={{ marginBottom: 6 }}>{sale.customer?.businessName ?? 'Cliente'}</h3>
          <p style={{ margin: '0 0 10px', color: 'var(--text-muted)' }}>
            {sale.invoiceFullNumber ?? 'Saldo inicial'} · Total {NumberUtils.formatCurrency(Number(sale.grandTotal), sale.currency ?? 'PEN')} ·{' '}
            Pagado {NumberUtils.formatCurrency(Number(sale.paidTotal), sale.currency ?? 'PEN')} ·{' '}
            <strong>Saldo {NumberUtils.formatCurrency(Number(sale.balanceDue), sale.currency ?? 'PEN')}</strong>
          </p>
          {sale.payments?.length ? (
            <div aria-label="Historial de pagos">
              <strong>Pagos registrados</strong>
              <ul style={{ marginTop: 6 }}>
                {sale.payments.map((payment, index) => (
                  <li key={`${payment.paid_at ?? 'payment'}-${index}`}>
                    {payment.paid_at ? `${payment.paid_at.slice(0, 10)} · ` : ''}
                    {payment.method_name ?? 'Pago'} · {NumberUtils.formatCurrency(Number(payment.amount ?? 0), sale.currency ?? 'PEN')}
                    {payment.reference ? ` · Ref. ${payment.reference}` : ''}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>
      ) : null}
      <form
        style={{ width: '100%' }}
        onSubmit={(e) => {
          e.preventDefault();
          registerCollection();
        }}
      >
        <div className="mb-3">
          <FormField label={<>Monto <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>*</span></>} htmlFor="sale-collect-amount">
            <TextField
              value={amountValue}
              onChange={(event) => setAmountValue(event.target.value)}
              disabled={!sale || isProcessing}
              id="sale-collect-amount"
              type="number"
              min={0.01}
              max={sale ? Number(sale.balanceDue) : undefined}
              step={0.01}
              required
              placeholder="0.00"
            />
          </FormField>
        </div>

        <div className="mb-3">
          <FormField label={<>Método de pago <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>*</span></>} htmlFor="sale-collect-method">
            <EntityDropdown
              url={API + 'payment-methods'}
              filters={[{ field: 'settlementBehavior', operator: '=', value: 'COLLECTED' }, { field: 'isActive', operator: '=', value: 1 }]}
              value={paymentMethodId}
              onChange={setPaymentMethodId}
              placeholder="Selecciona un método de pago"
              searchExpr={['name']}
            />
          </FormField>
        </div>

        <div className="mb-3">
          <FormField label="Referencia" htmlFor="sale-collect-reference">
            <TextField ref={referenceRef} id="sale-collect-reference" placeholder="N° de operación" />
          </FormField>
        </div>

        <Button
          type="submit"
          variant="success"
          fullWidth
          loading={isProcessing}
          disabled={!sale || Number(sale.balanceDue) <= 0}
          data-testid="sale-collect-submit-btn"
        >
          {isProcessing ? 'Procesando...' : 'Registrar cobro'}
        </Button>
      </form>
    </AppDialog>
  );
};
