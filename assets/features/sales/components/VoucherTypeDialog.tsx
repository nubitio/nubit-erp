import React, { useState } from 'react';
import ReactDOM from 'react-dom/client';
import { AppDialog, Button, Chip } from '@nubitio/ui';
import { useScreenSize } from '@nubitio/admin';

export type RequestedVoucherType = 'AUTO' | 'BOLETA' | 'FACTURA';

interface VoucherTypeDialogProps {
  onConfirm: (result: RequestedVoucherType) => void;
  onCancel?: () => void;
}

const OPTIONS: { value: RequestedVoucherType; label: string }[] = [
  { value: 'AUTO', label: 'Automático (por RUC del cliente)' },
  { value: 'BOLETA', label: 'Boleta' },
  { value: 'FACTURA', label: 'Factura' },
];

const VoucherTypeDialogInner = ({ onConfirm, onCancel }: VoucherTypeDialogProps) => {
  const { isXSmall } = useScreenSize();
  const [visible, setVisible] = useState(true);
  const [selected, setSelected] = useState<RequestedVoucherType>('AUTO');

  const handleConfirm = () => {
    onConfirm(selected);
    setVisible(false);
  };

  const handleCancel = () => {
    onCancel?.();
    setVisible(false);
  };

  return (
    <AppDialog
      title="Generar comprobante"
      fullScreen={isXSmall}
      width={isXSmall ? undefined : 420}
      visible={visible}
      showCloseButton={false}
      closeOnOutsideClick={false}
      closeOnEscape
      onClose={handleCancel}
      footer={
        <>
          <Button variant="secondary" onClick={handleCancel}>
            Cancelar
          </Button>
          <Button variant="primary" onClick={handleConfirm}>
            Generar
          </Button>
        </>
      }
    >
      <div>
        <p>Tipo de comprobante a emitir:</p>
        <div
          role="group"
          aria-label="Tipo de comprobante"
          style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}
        >
          {OPTIONS.map((option) => (
            <Chip
              key={option.value}
              label={option.label}
              active={selected === option.value}
              size="sm"
              onClick={() => setSelected(option.value)}
            />
          ))}
        </div>
      </div>
    </AppDialog>
  );
};

/**
 * Imperative helper — resolves with the chosen voucher type, or null if cancelled.
 *
 * Regímen NRUS o clientes sin RUC de 11 dígitos rechazan "Factura" en el
 * backend con un mensaje claro; este diálogo no necesita conocer el régimen
 * del tenant ni el RUC del cliente por adelantado.
 */
export const voucherTypePrompt = (): Promise<RequestedVoucherType | null> =>
  new Promise((resolve) => {
    const container = document.createElement('div');
    document.body.appendChild(container);
    const root = ReactDOM.createRoot(container);

    const cleanup = () => {
      root.unmount();
      container.remove();
    };

    root.render(
      <VoucherTypeDialogInner
        onConfirm={(result) => {
          resolve(result);
          cleanup();
        }}
        onCancel={() => {
          resolve(null);
          cleanup();
        }}
      />,
    );
  });
