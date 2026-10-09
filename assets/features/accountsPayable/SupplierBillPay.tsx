import { Badge, Button, Card, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import supplierBillService, { type SupplierBillPayment } from './services/supplierBillService';
import toast from '@shared/lib/SharedUtils';
import { formatMoney } from '@shared/lib/displayFormat';

const API = getCoreApiBaseUrl();

type SupplierBillPayParams = {
  id: string;
};

type SupplierBillSummary = {
  supplier?: { businessName?: string };
  number?: string | null;
  currency?: string;
  balanceDue?: string;
  payments?: SupplierBillPayment[];
};

export const SupplierBillPay = () => {
  const amountRef = useRef<HTMLInputElement>(null);
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [bill, setBill] = useState<SupplierBillSummary | null>(null);
  const [loadError, setLoadError] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);
  const { id } = useParams<SupplierBillPayParams>();
  const navigate = useNavigate();

  useEffect(() => {
    if (!id) return;
    supplierBillService()
      .get(id)
      .then(({ data }) => setBill(data))
      .catch(() => setLoadError(true));
  }, [id, reloadKey]);

  const reversePayment = (payment: SupplierBillPayment) => {
    if (!id || payment.reversedAt || !window.confirm('¿Reversar este pago?')) return;

    supplierBillService()
      .reverse(id, payment.id)
      .then(() => {
        toast('Pago reversado', 'success');
        setReloadKey((value) => value + 1);
      })
      .catch(() => toast('No se pudo reversar el pago.', 'error'));
  };

  const registerPayment = () => {
    const raw = amountRef.current?.value;
    const amount = raw !== undefined && raw !== '' ? parseFloat(raw) : NaN;

    if (id === undefined || isNaN(amount) || paymentMethodId === null) {
      toast('Completa el monto y el método de pago', 'error');
      return;
    }

    setIsProcessing(true);
    supplierBillService()
      .pay(id, {
        amount,
        paymentMethodId: parseInt(paymentMethodId, 10),
        reference: referenceRef.current?.value || undefined,
      })
      .then(() => {
        toast('Pago registrado', 'success');
        navigate('/accounts-payable');
      })
      .catch(() =>
        toast('No se pudo registrar el pago. Revisa el saldo y el método seleccionado.', 'error'),
      )
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <Card title="Registrar pago de factura">
      {loadError && <p role="alert">No se pudo cargar el contexto de la factura.</p>}
      {bill && (
        <div style={{ marginBottom: 16 }}>
          <strong>{bill.supplier?.businessName ?? 'Proveedor'}</strong>
          <p style={{ margin: '4px 0', color: 'var(--text-muted)' }}>
            Documento {bill.number ?? 'sin número'} · Saldo pendiente:{' '}
            {formatMoney(bill.balanceDue ?? '0', bill.currency ?? 'PEN')}
          </p>
        </div>
      )}
      {(bill?.payments?.length ?? 0) > 0 && (
        <section aria-label="Historial de pagos" style={{ marginBottom: 16 }}>
          <strong>Historial de pagos</strong>
          <ul style={{ margin: '8px 0', paddingLeft: 18 }}>
            {bill?.payments?.map((payment) => (
              <li key={payment.id} style={{ marginBottom: 6 }}>
                {payment.paidAt} · {formatMoney(payment.amount, bill.currency ?? 'PEN')}{' '}
                {payment.reference ? `· ${payment.reference} ` : ''}
                {payment.reversedAt ? (
                  <Badge variant="danger" outlined>
                    Reversado
                  </Badge>
                ) : (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => reversePayment(payment)}
                  >
                    Reversar
                  </Button>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}
      {bill && Number(bill.balanceDue ?? 0) <= 0 ? (
        <p role="status">
          Esta factura está totalmente pagada. Puedes reversar un pago desde el historial.
        </p>
      ) : (
        <form
          style={{ width: 320 }}
          onSubmit={(e) => {
            e.preventDefault();
            registerPayment();
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
              htmlFor="supplier-bill-pay-amount"
            >
              <TextField
                ref={amountRef}
                id="supplier-bill-pay-amount"
                type="number"
                min={0}
                max={bill?.balanceDue}
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
              htmlFor="supplier-bill-pay-method"
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
            <FormField label="Referencia" htmlFor="supplier-bill-pay-reference">
              <TextField
                ref={referenceRef}
                id="supplier-bill-pay-reference"
                placeholder="N° de operación"
              />
            </FormField>
          </div>

          <Button
            type="submit"
            variant="success"
            fullWidth
            loading={isProcessing}
            data-testid="supplier-bill-pay-submit-btn"
          >
            {isProcessing ? 'Procesando...' : 'Registrar pago'}
          </Button>
        </form>
      )}
    </Card>
  );
};
