import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { useIdempotencyKey } from '@shared/lib/idempotency';
import { saleEndpoints } from '../api/saleEndpoints';
import { saleDocument, saleMoney, type SaleDetail } from './saleDetailModel';

const METHOD_FILTERS = [
  { field: 'settlementBehavior', operator: '=', value: 'COLLECTED' },
  { field: 'isActive', operator: '=', value: 1 },
];
const METHOD_SEARCH = ['name'];

export function SaleCollectPaymentDialog({
  sale,
  onClose,
  onDone,
}: {
  sale: SaleDetail;
  onClose: () => void;
  onDone: () => void;
}) {
  const [amount, setAmount] = useState(sale.balanceDue);
  const [method, setMethod] = useState<string | null>(null);
  const [reference, setReference] = useState('');
  // Same key while the form is unchanged: a retry after a timeout cannot collect twice.
  const idempotency = useIdempotencyKey(`${sale.id}|${amount}|${method}|${reference}`);
  const payment = useMutation({
    mutationFn: () =>
      httpClient.post(
        `${saleEndpoints.detail(sale.id)}/collect`,
        {
          id: sale.id,
          amount,
          paymentMethodId: Number(method),
          reference: reference.trim() || null,
        },
        { headers: { 'Idempotency-Key': idempotency.current() } },
      ),
    onSuccess: () => {
      idempotency.renew();
      onDone();
    },
  });
  return (
    <AppDialog
      open
      title="Registrar pago de esta venta"
      onClose={() => {
        if (!payment.isPending) onClose();
      }}
      width={560}
      closeOnOutsideClick={false}
      closeOnEscape={!payment.isPending}
      footer={
        <>
          <Button onClick={onClose} disabled={payment.isPending}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form="sale-collect-payment-form"
            variant="primary"
            disabled={!method || payment.isPending}
            loading={payment.isPending}
          >
            Confirmar pago
          </Button>
        </>
      }
    >
      <p>
        {saleDocument(sale)} · Saldo: {saleMoney(sale.balanceDue, sale.currency)}
      </p>
      <p>El pago se aplica únicamente a esta venta.</p>
      <form
        className="sale-collect-form"
        id="sale-collect-payment-form"
        onSubmit={(event) => {
          event.preventDefault();
          if (method && amount && !payment.isPending) payment.mutate();
        }}
      >
        <FormField label={`Monto (${sale.currency})`} htmlFor="sale-collect-amount">
          <TextField
            id="sale-collect-amount"
            type="number"
            min="0.01"
            max={sale.balanceDue}
            step="0.01"
            required
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            disabled={payment.isPending}
          />
        </FormField>
        <FormField label="Método de pago" htmlFor="sale-collect-method">
          <EntityDropdown
            id="sale-collect-method"
            label="Método de pago"
            url={`${getCoreApiBaseUrl()}payment-methods`}
            filters={METHOD_FILTERS}
            value={method}
            onChange={setMethod}
            placeholder="Selecciona un método de pago"
            searchExpr={METHOD_SEARCH}
            disabled={payment.isPending}
          />
        </FormField>
        <FormField label="Referencia" htmlFor="sale-collect-reference">
          <TextField
            id="sale-collect-reference"
            maxLength={120}
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            disabled={payment.isPending}
          />
        </FormField>
        {payment.isError && (
          <p role="alert">
            No se pudo registrar el pago. Revisa el método, el saldo y que tu caja esté abierta si
            cobras en efectivo.
          </p>
        )}
      </form>
    </AppDialog>
  );
}
