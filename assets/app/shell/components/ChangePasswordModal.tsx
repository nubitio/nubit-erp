import React, { useCallback, useRef, useState } from 'react';
import { Popup, ToolbarItem } from 'devextreme-react/popup';
import Form, { Item, RequiredRule, StringLengthRule, FormRef } from 'devextreme-react/form';
import { Button } from 'devextreme-react';
import LoadIndicator from 'devextreme-react/load-indicator';

import { authService } from '@features/auth';
import toast from '@shared/lib/SharedUtils';
import { useScreenSize } from '@utils/media-query';

interface ChangePasswordModalProps {
  visible: boolean;
  onClose: () => void;
}

interface FormData {
  currentPassword: string;
  newPassword: string;
  confirmPassword: string;
}

export const ChangePasswordModal = ({ visible, onClose }: ChangePasswordModalProps) => {
  const { isXSmall } = useScreenSize();
  const formRef = useRef<FormRef | null>(null);
  const formData = useRef<FormData>({ currentPassword: '', newPassword: '', confirmPassword: '' });
  const [loading, setLoading] = useState(false);
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  const resetForm = useCallback(() => {
    formData.current = { currentPassword: '', newPassword: '', confirmPassword: '' };
    formRef.current?.instance().option('formData', formData.current);
    setShowCurrent(false);
    setShowNew(false);
    setShowConfirm(false);
  }, []);

  const handleClose = useCallback(() => {
    resetForm();
    onClose();
  }, [onClose, resetForm]);

  const handleSubmit = useCallback(() => {
    const validation = formRef.current?.instance().validate();
    if (!validation?.isValid) return;

    const { currentPassword, newPassword, confirmPassword } = formData.current;

    if (newPassword !== confirmPassword) {
      toast('Las contraseñas nuevas no coinciden.', 'error');
      return;
    }

    setLoading(true);
    authService.changePassword(currentPassword, newPassword).subscribe({
      next: () => {
        toast('Contraseña actualizada correctamente.', 'success');
        handleClose();
      },
      error: (err) => {
        const message =
          err?.response?.data?.message ||
          'No se pudo cambiar la contraseña. Inténtalo nuevamente.';
        toast(message, 'error');
        setLoading(false);
      },
      complete: () => {
        setLoading(false);
      },
    });
  }, [handleClose]);

  const passwordEditorOptions = useCallback(
    (show: boolean, onToggle: () => void, placeholder: string) => ({
      stylingMode: 'outlined',
      placeholder,
      mode: show ? 'text' : 'password',
      disabled: loading,
      buttons: [
        {
          name: 'toggle',
          location: 'after',
          options: {
            icon: show ? 'ph ph-eye' : 'ph ph-eye-slash',
            type: 'default',
            stylingMode: 'text',
            onClick: onToggle,
          },
        },
      ],
    }),
    [loading],
  );

  return (
    <Popup
      title="Cambiar contraseña"
      visible={visible}
      width={420}
      height="auto"
      fullScreen={isXSmall}
      showCloseButton={!loading}
      hideOnOutsideClick={!loading}
      onHiding={handleClose}
    >
      <Form
        ref={formRef}
        // eslint-disable-next-line react-hooks/refs -- DevExtreme binding pattern: formData ref holds mutable form state intentionally
        formData={formData.current}
        disabled={loading}
        labelMode="outside"
        showColonAfterLabel={false}
        showRequiredMark={false}
      >
        <Item
          dataField="currentPassword"
          label={{ text: 'Contraseña actual' }}
          editorType="dxTextBox"
          editorOptions={passwordEditorOptions(showCurrent, () => setShowCurrent((s) => !s), 'Contraseña actual')}
        >
          <RequiredRule message="La contraseña actual es requerida." />
        </Item>
        <Item
          dataField="newPassword"
          label={{ text: 'Nueva contraseña' }}
          editorType="dxTextBox"
          editorOptions={passwordEditorOptions(showNew, () => setShowNew((s) => !s), 'Nueva contraseña')}
        >
          <RequiredRule message="La nueva contraseña es requerida." />
          <StringLengthRule min={6} message="La contraseña debe tener al menos 6 caracteres." />
        </Item>
        <Item
          dataField="confirmPassword"
          label={{ text: 'Confirmar nueva contraseña' }}
          editorType="dxTextBox"
          editorOptions={passwordEditorOptions(showConfirm, () => setShowConfirm((s) => !s), 'Confirmar contraseña')}
        >
          <RequiredRule message="Confirma tu nueva contraseña." />
        </Item>
      </Form>

      <ToolbarItem toolbar="bottom" location="after">
        <div style={{ display: 'flex', gap: '8px', padding: '8px 0' }}>
          <Button
            text="Cancelar"
            stylingMode="outlined"
            disabled={loading}
            onClick={handleClose}
          />
          <Button
            type="default"
            stylingMode="outlined"
            disabled={loading}
            onClick={handleSubmit}
          >
            <span className="dx-button-text">
              {loading ? <LoadIndicator width="20px" height="20px" visible /> : 'Guardar'}
            </span>
          </Button>
        </div>
      </ToolbarItem>
    </Popup>
  );
};
