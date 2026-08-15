import { useState } from 'react';
import { AppDialog, Button, FormField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import saleQuoteService from './services/saleQuoteService';

const API = getCoreApiBaseUrl();

interface SaleQuoteConvertDialogProps {
  quoteId: string | null;
  customerLabel?: string;
  grandTotal?: string;
  itemCount?: number;
  onClose: () => void;
  onConverted: (saleId: string) => void;
}

export const SaleQuoteConvertDialog = ({
  quoteId,
  customerLabel,
  grandTotal,
  itemCount,
  onClose,
  onConverted,
}: SaleQuoteConvertDialogProps) => {
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);

  const handleConvert = () => {
    if (!quoteId) return;

    setIsProcessing(true);
    saleQuoteService()
      .convert(quoteId, paymentMethodId !== null ? parseInt(paymentMethodId, 10) : undefined)
      .then((response) => {
        toast('Cotización convertida a venta', 'success');
        onConverted(response.data.id);
      })
      .catch((err: unknown) => {
        const message = err instanceof Error ? err.message : 'No se pudo convertir la cotización.';
        toast(message, 'error');
      })
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <AppDialog
      title="Convertir a venta"
      open={quoteId !== null}
      width={420}
      height="auto"
      showCloseButton={!isProcessing}
      closeOnOutsideClick={!isProcessing}
      onClose={onClose}
      footer={
        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
          <Button variant="secondary" onClick={onClose} disabled={isProcessing}>
            Cancelar
          </Button>
          <Button variant="success" onClick={handleConvert} loading={isProcessing}>
            Convertir
          </Button>
        </div>
      }
    >
      <div style={{ marginBottom: 12 }}>
        {customerLabel && <p>Cliente: {customerLabel}</p>}
        {itemCount !== undefined && <p>Ítems: {itemCount}</p>}
        {grandTotal !== undefined && <p>Total: {grandTotal}</p>}
      </div>
      <FormField label="Método de pago" htmlFor="sale-quote-convert-payment-method">
        <EntityDropdown
          url={API + 'payment-methods'}
          value={paymentMethodId}
          onChange={setPaymentMethodId}
          placeholder="Efectivo (por defecto)"
          searchExpr={['name']}
        />
      </FormField>
    </AppDialog>
  );
};
