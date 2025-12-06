import React, { useState } from 'react';
import { Popup, ToolbarItem } from 'devextreme-react/popup';
import Form, { SimpleItem } from 'devextreme-react/form';
import { Button } from 'devextreme-react';
import ReactDOM from 'react-dom/client';
import { useScreenSize } from '../../../utils/media-query';

interface PromptDialogProps {
  title: string;
  message?: string;
  editorType?: 'dxDateBox' | 'dxNumberBox' | 'dxTextBox';
  editorOptions?: any;
  onConfirm: (formData: any) => void;
  onCancel?: () => void;
}

export const PromptDialog = ({
  title,
  message,
  editorType,
  editorOptions,
  onConfirm,
  onCancel,
}: PromptDialogProps) => {
  const { isXSmall } = useScreenSize();
  const [visible, setVisible] = useState<boolean>(true);
  const [formData, setFormData] = useState<any>({ prompt: editorOptions?.defaultValue ?? '' });

  const handleConfirm = () => {
    onConfirm(formData);
    setVisible(false);
    setFormData({});
  };

  const handleCancel = () => {
    onCancel?.();
    setVisible(false);
    setFormData({});
  };

  return (
    <Popup
      visible={visible}
      title={title}
      fullScreen={isXSmall}
      maxWidth={400}
      maxHeight={350}
      height={'auto'}
      onHiding={handleCancel}
      wrapperAttr={{ class: `form-popup` }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className={'form-popup-buttons-container'}>
          <Button text="Cancelar" stylingMode="outlined" onClick={handleCancel} />
          <Button text="Confirmar" stylingMode="outlined" type="default" onClick={handleConfirm} />
        </div>
      </ToolbarItem>
      <Form formData={formData} labelMode={'floating'}>
        <SimpleItem
          dataField="prompt"
          label={{ text: message ?? 'Ingrese un valor' }}
          editorType={editorType ?? 'dxTextBox'}
          editorOptions={{
            onEnterKey: handleConfirm,
            stylingMode: 'outlined',
            ...editorOptions,
          }}
        />
      </Form>
    </Popup>
  );
};

export const prompt = (
  title: string,
  message?: string,
  inputType: 'text' | 'number' | 'password' = 'text',
  defaultValue?: any,
) => {
  return new Promise<string>((resolve) => {
    const container = document.createElement('div');
    document.body.appendChild(container);
    const root = ReactDOM.createRoot(container);

    const handleConfirm = (formData: any) => {
      resolve(formData.prompt);
      root.unmount();
    };

    const handleCancel = () => {
      resolve('');
      root.unmount();
    };

    const promptDialog = (
      <PromptDialog
        title={title}
        message={message}
        editorType={
          inputType === 'number'
            ? 'dxNumberBox'
            : inputType === 'password'
              ? 'dxTextBox'
              : 'dxTextBox'
        }
        editorOptions={
          inputType === 'password'
            ? {
                mode: 'password',
                defaultValue: defaultValue,
              }
            : { defaultValue: defaultValue }
        }
        onConfirm={handleConfirm}
        onCancel={handleCancel}
      />
    );

    root.render(promptDialog);
  });
};
