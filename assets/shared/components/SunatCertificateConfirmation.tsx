import { useId, useState, type ComponentProps } from 'react';
import { AppDialog, Button, FormField, FormLayout, IconButton, TextField } from '@nubitio/ui';
import type { CoreHttpError } from '@nubitio/core';
import './SunatCertificateConfirmation.scss';

export const SUNAT_CERTIFICATE_ACCEPT = '.p12,.pfx,.pem,.crt,.cer';

export function validateSunatCertificate(solUser: string, solPass: string, file: File | null) {
  return {
    solUser: solUser.trim() === '' ? 'Ingresa el usuario SOL.' : undefined,
    solPass: solPass.trim() === '' ? 'Ingresa la clave SOL.' : undefined,
    certificate: !file
      ? 'Adjunta el certificado digital.'
      : !/\.(p12|pfx|pem|crt|cer)$/i.test(file.name)
        ? 'Selecciona un certificado P12, PFX o PEM.'
        : file.size === 0
          ? 'El archivo está vacío.'
          : undefined,
  };
}

export function SunatPasswordField({ className, ...props }: ComponentProps<typeof TextField>) {
  const [visible, setVisible] = useState(false);
  return (
    <div className="sunat-password-field">
      <TextField
        {...props}
        className={
          className ? `sunat-password-field__input ${className}` : 'sunat-password-field__input'
        }
        type={visible ? 'text' : 'password'}
      />
      <IconButton
        type="button"
        className="sunat-password-field__toggle"
        label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
        icon={visible ? 'ph ph-eye-slash' : 'ph ph-eye'}
        disabled={props.disabled}
        onClick={() => setVisible((value) => !value)}
      />
    </div>
  );
}

export function SunatCertificateConfirmation({
  onClose,
  onConfirm,
  onError,
}: {
  onClose: () => void;
  onConfirm: (password: string) => Promise<void>;
  onError: (message: string) => void;
}) {
  const id = useId();
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string>();
  const [saving, setSaving] = useState(false);

  const submit = async () => {
    if (saving) return;
    if (password === '') {
      setError('Ingresa la contraseña actual de tu cuenta ERP.');
      return;
    }
    setError(undefined);
    setSaving(true);
    try {
      await onConfirm(password);
      onClose();
    } catch (failure) {
      const { data, status } = failure as CoreHttpError;
      if (data?.code === 'certificate_reauthentication_required') {
        setError('La contraseña actual del ERP es incorrecta.');
      } else {
        const detail = data?.error ?? data?.detail ?? data?.message;
        onError(
          status === 401
            ? 'Tu sesión ha expirado. Vuelve a iniciar sesión.'
            : status === 403
              ? 'No tienes permiso para configurar el certificado SUNAT.'
              : typeof detail === 'string' && detail !== ''
                ? detail
                : 'No se pudo guardar el certificado SUNAT. Inténtalo de nuevo.',
        );
        onClose();
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppDialog
      open
      title="Confirma tu identidad"
      width={440}
      closeOnOutsideClick={false}
      closeOnEscape={!saving}
      showCloseButton={!saving}
      onClose={() => {
        if (!saving) onClose();
      }}
      footer={
        <>
          <Button type="button" variant="secondary" disabled={saving} onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            variant="primary"
            form={id}
            loading={saving}
            data-testid="confirm-sunat-certificate"
          >
            Guardar certificado
          </Button>
        </>
      }
    >
      <FormLayout
        id={id}
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <FormField
          label="Contraseña actual del ERP"
          htmlFor={id + '-password'}
          helpText="Confirma el cambio del certificado que firma tus comprobantes."
          error={error}
          required
        >
          <SunatPasswordField
            id={id + '-password'}
            required
            autoComplete="current-password"
            autoFocus
            disabled={saving}
            invalid={Boolean(error)}
            value={password}
            onChange={(event) => {
              setPassword(event.target.value);
              setError(undefined);
            }}
          />
        </FormField>
      </FormLayout>
    </AppDialog>
  );
}
