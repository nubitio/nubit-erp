import React, { useCallback, useState } from 'react';

import { authService } from '@features/auth';
import toast from '@shared/lib/SharedUtils';
import { useScreenSize } from '@nubit/admin';
import { Button, IconButton, TextField } from '@nubit/ui';

import { AppDialog } from './AppDialog';

import './ChangePasswordModal.scss';

interface ChangePasswordModalProps {
  visible: boolean;
  onClose: () => void;
}

interface FormData {
  currentPassword: string;
  newPassword: string;
  confirmPassword: string;
}

type FormErrors = Partial<Record<keyof FormData, string>>;

const initialFormData: FormData = {
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
};

export const ChangePasswordModal = ({ visible, onClose }: ChangePasswordModalProps) => {
  const { isXSmall } = useScreenSize();
  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [errors, setErrors] = useState<FormErrors>({});
  const [loading, setLoading] = useState(false);
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  const resetForm = useCallback(() => {
    setFormData(initialFormData);
    setErrors({});
    setShowCurrent(false);
    setShowNew(false);
    setShowConfirm(false);
  }, []);

  const handleClose = useCallback(() => {
    resetForm();
    onClose();
  }, [onClose, resetForm]);

  const updateField = (field: keyof FormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: undefined }));
  };

  const validate = useCallback(() => {
    const nextErrors: FormErrors = {};
    if (!formData.currentPassword) {
      nextErrors.currentPassword = 'La contraseña actual es requerida.';
    }
    if (!formData.newPassword) {
      nextErrors.newPassword = 'La nueva contraseña es requerida.';
    } else if (formData.newPassword.length < 6) {
      nextErrors.newPassword = 'La contraseña debe tener al menos 6 caracteres.';
    }
    if (!formData.confirmPassword) {
      nextErrors.confirmPassword = 'Confirma tu nueva contraseña.';
    } else if (formData.newPassword !== formData.confirmPassword) {
      nextErrors.confirmPassword = 'Las contraseñas nuevas no coinciden.';
    }

    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }, [formData]);

  const handleSubmit = useCallback(() => {
    if (!validate()) return;

    setLoading(true);
    authService
      .changePassword(formData.currentPassword, formData.newPassword)
      .then(() => {
        toast('Contraseña actualizada correctamente.', 'success');
        handleClose();
      })
      .catch((err: unknown) => {
        const anyErr = err as { data?: { message?: string } };
        const message =
          anyErr?.data?.message ||
          'No se pudo cambiar la contraseña. Inténtalo nuevamente.';
        toast(message, 'error');
      })
      .finally(() => {
        setLoading(false);
      });
  }, [formData, handleClose, validate]);

  const renderPasswordField = (
    field: keyof FormData,
    label: string,
    placeholder: string,
    showValue: boolean,
    onToggle: () => void,
  ) => {
    const error = errors[field];

    return (
      <label className="change-password-field">
        <span className="change-password-field__label">{label}</span>
        <span className={`change-password-field__control${error ? ' has-error' : ''}`}>
          <TextField
            className="change-password-field__input"
            type={showValue ? 'text' : 'password'}
            placeholder={placeholder}
            value={formData[field]}
            disabled={loading}
            invalid={!!error}
            aria-invalid={!!error}
            aria-describedby={error ? `${field}-error` : undefined}
            onChange={(event) => updateField(field, event.target.value)}
          />
          <IconButton
            className="change-password-field__toggle"
            label={showValue ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            icon={showValue ? 'ph ph-eye' : 'ph ph-eye-slash'}
            disabled={loading}
            onClick={onToggle}
          />
        </span>
        {error && <span className="change-password-field__error" id={`${field}-error`}>{error}</span>}
      </label>
    );
  };

  return (
    <AppDialog
      title="Cambiar contraseña"
      visible={visible}
      width={420}
      height="auto"
      fullScreen={isXSmall}
      showCloseButton={!loading}
      closeOnOutsideClick={!loading}
      closeOnEscape={!loading}
      onClose={handleClose}
      className="change-password-modal"
      footer={
        <>
          <Button
            variant="secondary"
            disabled={loading}
            onClick={handleClose}
          >
            Cancelar
          </Button>
          <Button
            variant="primary"
            loading={loading}
            onClick={handleSubmit}
          >
            Guardar
          </Button>
        </>
      }
    >
      <div className="change-password-modal__content">
        <div className="change-password-modal__form">
          {renderPasswordField(
            'currentPassword',
            'Contraseña actual',
            'Contraseña actual',
            showCurrent,
            () => setShowCurrent((prev) => !prev),
          )}
          {renderPasswordField(
            'newPassword',
            'Nueva contraseña',
            'Nueva contraseña',
            showNew,
            () => setShowNew((prev) => !prev),
          )}
          {renderPasswordField(
            'confirmPassword',
            'Confirmar nueva contraseña',
            'Confirmar contraseña',
            showConfirm,
            () => setShowConfirm((prev) => !prev),
          )}
        </div>
      </div>
    </AppDialog>
  );
};
