import { useState } from 'react';
import { AppDialog, Button, FormField, FormLayout, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { profileService } from './profileService';
import { violationsOf } from './formErrors';
import { useAcceptProfile } from './useProfile';

const FORM_ID = 'profile-email-form';
const FIELDS = ['email', 'currentPassword'] as const;

/** The e-mail recovers the password, so changing it asks for the current one. */
export const ChangeEmailDialog = ({
  current,
  onClose,
}: {
  current: string | null;
  onClose: () => void;
}) => {
  const accept = useAcceptProfile();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  const submit = async () => {
    setSaving(true);
    setErrors({});
    try {
      accept((await profileService.changeEmail(email, password)).data);
      toast('Correo actualizado', 'success');
      onClose();
    } catch (failure) {
      setErrors(violationsOf(failure, FIELDS).fields);
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppDialog
      open
      title="Cambiar correo"
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
            form={FORM_ID}
            variant="primary"
            loading={saving}
            disabled={email.trim() === '' || password === ''}
          >
            Cambiar correo
          </Button>
        </>
      }
    >
      <FormLayout
        id={FORM_ID}
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <p className="settings-muted">
          {current ? `Ahora: ${current}. ` : 'Aún no tienes un correo. '}Lo usamos para recuperar tu
          contraseña.
        </p>
        <FormField label="Correo nuevo" htmlFor="profile-email" error={errors['email']}>
          <TextField
            id="profile-email"
            type="email"
            autoComplete="email"
            autoFocus
            value={email}
            invalid={Boolean(errors['email'])}
            onChange={(event) => setEmail(event.target.value)}
          />
        </FormField>
        <FormField
          label="Contraseña actual"
          htmlFor="profile-email-password"
          helpText="La pedimos para confirmar que eres tú."
          error={errors['currentPassword']}
        >
          <TextField
            id="profile-email-password"
            type="password"
            autoComplete="current-password"
            value={password}
            invalid={Boolean(errors['currentPassword'])}
            onChange={(event) => setPassword(event.target.value)}
          />
        </FormField>
      </FormLayout>
    </AppDialog>
  );
};
