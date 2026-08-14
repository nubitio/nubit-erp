import { Button, Card, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import supplierBillService from './services/supplierBillService';
import toast from '@shared/lib/SharedUtils';

const API = getCoreApiBaseUrl();

type SupplierBillPayParams = {
  id: string;
};

export const SupplierBillPay = () => {
  const amountRef = useRef<HTMLInputElement>(null);
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const { id } = useParams<SupplierBillPayParams>();
  const navigate = useNavigate();

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
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <Card title="Registrar pago de factura">
      <form
        style={{ width: 320 }}
        onSubmit={(e) => {
          e.preventDefault();
          registerPayment();
        }}
      >
        <div className="mb-3">
          <FormField label={<>Monto <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>*</span></>} htmlFor="supplier-bill-pay-amount">
            <TextField
              ref={amountRef}
              id="supplier-bill-pay-amount"
              type="number"
              min={0}
              step={0.01}
              required
              placeholder="0.00"
            />
          </FormField>
        </div>

        <div className="mb-3">
          <FormField label={<>Método de pago <span aria-hidden="true" style={{ color: 'var(--error-color)' }}>*</span></>} htmlFor="supplier-bill-pay-method">
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
          <FormField label="Referencia" htmlFor="supplier-bill-pay-reference">
            <TextField ref={referenceRef} id="supplier-bill-pay-reference" placeholder="N° de operación" />
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
    </Card>
  );
};
