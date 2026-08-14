import { Button, Card, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import saleCollectService from './services/saleCollectService';
import toast from '@shared/lib/SharedUtils';

const API = getCoreApiBaseUrl();

type SaleCollectPaymentParams = {
  id: string;
};

export const SaleCollectPayment = () => {
  const amountRef = useRef<HTMLInputElement>(null);
  const referenceRef = useRef<HTMLInputElement>(null);
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const { id } = useParams<SaleCollectPaymentParams>();
  const navigate = useNavigate();

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
