import React, { useState } from 'react';
import { Popup, ToolbarItem } from 'devextreme-react/popup';
import Form, { SimpleItem } from 'devextreme-react/form';
import { Button } from 'devextreme-react';
import ReactDOM from 'react-dom/client';
import { useScreenSize } from '@utils/media-query';

export type VoidDialogResult = {
  reason: string;
  restoreStock: boolean;
};

interface VoidDialogProps {
  onConfirm: (result: VoidDialogResult) => void;
  onCancel?: () => void;
}

const VoidDialogInner = ({ onConfirm, onCancel }: VoidDialogProps) => {
  const { isXSmall } = useScreenSize();
  const [visible, setVisible] = useState(true);
  const [formData, setFormData] = useState<VoidDialogResult>({
    reason: '',
    restoreStock: false,
  });

  const handleConfirm = () => {
    if (!formData.reason?.trim()) return;
    onConfirm(formData);
    setVisible(false);
  };

  const handleCancel = () => {
    onCancel?.();
    setVisible(false);
  };

  return (
    <Popup
      visible={visible}
      title="Anular comprobante"
      fullScreen={isXSmall}
      maxWidth={420}
      height="auto"
      onHiding={handleCancel}
      wrapperAttr={{ class: 'form-popup' }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className="form-popup-buttons-container">
          <Button text="Cancelar" stylingMode="outlined" onClick={handleCancel} />
          <Button
            text="Confirmar"
            stylingMode="outlined"
            type="default"
            onClick={handleConfirm}
            disabled={!formData.reason?.trim()}
          />
        </div>
      </ToolbarItem>

      <Form
        formData={formData}
        labelMode="floating"
        onFieldDataChanged={(e) =>
          setFormData((prev) => ({ ...prev, [e.dataField as string]: e.value }))
        }
      >
        <SimpleItem
          dataField="reason"
          label={{ text: 'Motivo de anulación' }}
          editorType="dxTextBox"
          editorOptions={{
            stylingMode: 'outlined',
            onEnterKey: handleConfirm,
          }}
          validationRules={[{ type: 'required', message: 'El motivo es obligatorio' }]}
        />
        <SimpleItem
          dataField="restoreStock"
          label={{ text: 'Restaurar stock de productos' }}
          editorType="dxCheckBox"
          editorOptions={{ text: 'Restaurar stock al anular' }}
        />
      </Form>
    </Popup>
  );
};

/** Imperative helper — resolves with the result or null if cancelled. */
export const voidPrompt = (): Promise<VoidDialogResult | null> =>
  new Promise((resolve) => {
    const container = document.createElement('div');
    document.body.appendChild(container);
    const root = ReactDOM.createRoot(container);

    const cleanup = () => {
      root.unmount();
      container.remove();
    };

    root.render(
      <VoidDialogInner
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
