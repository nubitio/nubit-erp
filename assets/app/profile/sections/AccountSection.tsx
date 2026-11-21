import { useState } from 'react';
import { Button, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { ChangeEmailDialog } from '../ChangeEmailDialog';
import { ProfilePhoto } from '../ProfilePhoto';
import { profileService, type Profile } from '../profileService';
import { violationsOf } from '../formErrors';
import { useAcceptProfile } from '../useProfile';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';

const FIELDS = ['displayName', 'phone'] as const;

export const AccountSection = ({ profile }: { profile: Profile }) => {
  const accept = useAcceptProfile();
  const [displayName, setDisplayName] = useState(profile.displayName ?? '');
  const [phone, setPhone] = useState(profile.phone ?? '');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [emailOpen, setEmailOpen] = useState(false);

  const dirty =
    displayName.trim() !== (profile.displayName ?? '') || phone.trim() !== (profile.phone ?? '');

  const save = async () => {
    setSaving(true);
    setErrors({});
    try {
      const saved = (await profileService.update({ displayName, phone })).data;
      accept(saved);
      setDisplayName(saved.displayName ?? '');
      setPhone(saved.phone ?? '');
      toast('Datos guardados', 'success');
    } catch (failure) {
      setErrors(violationsOf(failure, FIELDS).fields);
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <SettingsGroup>
        <SettingsRow label="Foto de perfil" description="PNG, JPG o WebP, hasta 2 MB.">
          <ProfilePhoto profile={profile} />
        </SettingsRow>
        <SettingsRow
          label="Nombre"
          description="Así te ven los demás usuarios."
          htmlFor="profile-name"
        >
          <TextField
            id="profile-name"
            value={displayName}
            maxLength={120}
            invalid={Boolean(errors['displayName'])}
            placeholder="Cómo quieres que te llamemos"
            onChange={(event) => setDisplayName(event.target.value)}
          />
        </SettingsRow>
        {errors['displayName'] && <p className="settings-error">{errors['displayName']}</p>}
        <SettingsRow label="Teléfono" htmlFor="profile-phone">
          <TextField
            id="profile-phone"
            type="tel"
            value={phone}
            invalid={Boolean(errors['phone'])}
            placeholder="+51 987 654 321"
            onChange={(event) => setPhone(event.target.value)}
          />
        </SettingsRow>
        {errors['phone'] && <p className="settings-error">{errors['phone']}</p>}
        <SettingsRow label="Usuario" description="Con este nombre inicias sesión.">
          <span className="settings-muted">{profile.username}</span>
        </SettingsRow>
        <SettingsRow label="Correo" description="Lo usamos para recuperar tu contraseña.">
          <span className="settings-muted">{profile.email ?? 'Sin correo'}</span>
          <Button type="button" variant="secondary" size="sm" onClick={() => setEmailOpen(true)}>
            Cambiar
          </Button>
        </SettingsRow>
      </SettingsGroup>
      {dirty && (
        <div className="settings-actions">
          <Button
            type="button"
            variant="secondary"
            disabled={saving}
            onClick={() => {
              setDisplayName(profile.displayName ?? '');
              setPhone(profile.phone ?? '');
              setErrors({});
            }}
          >
            Descartar
          </Button>
          <Button type="button" variant="primary" loading={saving} onClick={() => void save()}>
            Guardar cambios
          </Button>
        </div>
      )}
      {emailOpen && (
        <ChangeEmailDialog current={profile.email} onClose={() => setEmailOpen(false)} />
      )}
    </>
  );
};
