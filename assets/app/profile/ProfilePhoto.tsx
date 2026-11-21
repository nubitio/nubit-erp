import { useRef, useState } from 'react';
import { Button } from '@nubitio/ui';
import { UserAvatar } from '@shared/components';
import toast from '@shared/lib/SharedUtils';
import { AVATAR_MAX_BYTES, AVATAR_TYPES, profileService, type Profile } from './profileService';
import { violationsOf } from './formErrors';
import { useAcceptProfile } from './useProfile';

/** The control of the "Foto de perfil" row: the photo itself, and what can be done with it. */
export const ProfilePhoto = ({ profile }: { profile: Profile }) => {
  const accept = useAcceptProfile();
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const name = profile.displayName || profile.username;

  const upload = async (file: File) => {
    setError(null);
    // The server checks the real content; this only saves a round trip for the obvious cases.
    if (!AVATAR_TYPES.includes(file.type)) {
      setError('Sube una imagen PNG, JPG o WebP.');
      return;
    }
    if (file.size > AVATAR_MAX_BYTES) {
      setError('La imagen supera los 2 MB.');
      return;
    }
    setBusy(true);
    try {
      accept((await profileService.uploadAvatar(file)).data);
      toast('Foto actualizada', 'success');
    } catch (failure) {
      setError(violationsOf(failure, ['file']).fields['file'] ?? null);
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    setBusy(true);
    setError(null);
    try {
      accept((await profileService.removeAvatar()).data);
      toast('Foto eliminada', 'success');
    } catch {
      /* HttpClient reported it */
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="profile-photo">
      <button
        type="button"
        className="profile-photo__avatar"
        aria-label={profile.avatarUrl ? 'Cambiar foto' : 'Subir foto'}
        disabled={busy}
        onClick={() => input.current?.click()}
      >
        <UserAvatar name={name} avatarUrl={profile.avatarUrl} size={56} />
        <span className="profile-photo__badge" aria-hidden="true">
          <i className="ph ph-camera" />
        </span>
      </button>
      <div className="profile-photo__actions">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          loading={busy}
          onClick={() => input.current?.click()}
        >
          {profile.avatarUrl ? 'Cambiar' : 'Subir foto'}
        </Button>
        {profile.avatarUrl && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={busy}
            onClick={() => void remove()}
          >
            Quitar
          </Button>
        )}
      </div>
      {error && (
        <p className="profile-photo__error" role="alert">
          {error}
        </p>
      )}
      <input
        ref={input}
        type="file"
        accept={AVATAR_TYPES.join(',')}
        hidden
        data-testid="profile-photo-input"
        onChange={(event) => {
          const target = event.target;
          const file = target.files?.[0];
          if (!file) return;
          // Clear the input only once the upload is done (so choosing the same file again fires
          // change), not before: resetting it can drop the file from under the request.
          void upload(file).finally(() => {
            target.value = '';
          });
        }}
      />
    </div>
  );
};
