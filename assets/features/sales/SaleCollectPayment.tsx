import { Button, Card, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import saleCollectService from './services/saleCollectService';
import toast from '@shared/lib/SharedUtils';
import { httpClient } from '@shared/api/HttpClient';
import { saleEndpoints } from './api/saleEndpoints';
import NumberUtils from '@shared/lib/NumberUtils';

const API = getCoreApiBaseUrl();

type SaleCollectPaymentParams = {
  id: string;
};

type CollectableSale = {
  customer?: { businessName?: string } | null;
  invoiceFullNumber?: string | null;
  currency?: string;
  grandTotal: string;
  paidTotal: string;
  balanceDue: string;
  payments?: Array<{ method_name?: string | null; amount?: string; paid_at?: string | null; reference?: string | null }>;
};

export const SaleCollectPayment = () => {
  const amountRef = useRef<HTMLInputElement>(null);
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [sale, setSale] = useState<CollectableSale | null>(null);
  const { id } = useParams<SaleCollectPaymentParams>();
  const navigate = useNavigate();

  useEffect(() => {
    if (!id) return;
    const controller = new AbortController();
    httpClient
      .get<CollectableSale>(saleEndpoints.detail(id), { signal: controller.signal })
      .then(({ data }) => {
        setSale(data);
        if (amountRef.current) amountRef.current.value = data.balanceDue;
      })
      .catch(() => toast('No se pudo cargar el estado de cuenta', 'error'));
    return () => controller.abort();
  }, [id]);

  const registerCollection = () => {
    const raw = amountRef.current?.value;
    const amount = raw !== undefined && raw !== '' ? parseFloat(raw) : NaN;

    if (id === undefined || isNaN(amount) || paymentMethodId === null) {
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
        navigate('/accounts-receivable');
      })
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <Card title="Registrar cobro de venta">
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
        style={{ width: 320 }}
        onSubmit={(e) => {
          e.preventDefault();
          registerCollection();
        }}
      >
        <div className="mb-3">
          <FormField label={<>Monto <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>*</span></>} htmlFor="sale-collect-amount">
            <TextField
              ref={amountRef}
              id="sale-collect-amount"
              type="number"
              min={0}
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
          data-testid="sale-collect-submit-btn"
        >
          {isProcessing ? 'Procesando...' : 'Registrar cobro'}
        </Button>
      </form>
    </Card>
  );
};
