import React, { useCallback, useEffect, useState } from 'react';

import { authService } from '@features/auth';
import { describeIdentityError } from '@features/auth/identityErrors';
import { classifyLoginError } from '@features/auth/loginErrors';
import toast from '@shared/lib/SharedUtils';
import { Button, TextField } from '@nubitio/ui';
import { QrCode } from '@shared/components';
import { downloadBlob } from '@shared/lib/downloadBlob';
import { useAuth } from '@app/contexts/auth';
import { recoveryCodesFile } from './recoveryCodesFile';

import { AppDialog } from './AppDialog';

import './TwoFactorModal.scss';

interface TwoFactorModalProps {
  visible: boolean;
  onClose: () => void;
}

type Status = { enrolled: boolean; pending: boolean; recoveryCodesLeft: number };
type Enrolment = { secret: string; uri: string; recoveryCodes: string[] };
type Mode = 'status' | 'confirm-enrol' | 'disable' | 'new-codes' | 'show-codes';

/** Groups a base32 secret in blocks of 4 so it can be typed into an authenticator app. */
export function groupSecret(secret: string): string {
  return secret
    .replace(/\s+/g, '')
    .replace(/(.{4})/g, '$1 ')
    .trim();
}

function codeError(err: unknown): string {
  const classified = classifyLoginError((err as { data?: { message?: string } })?.data?.message);
  return classified.text ?? describeIdentityError(err, 'No se pudo completar la acción.');
}

const TwoFactorDialog = ({ visible, onClose }: TwoFactorModalProps) => {
  const { user } = useAuth();
  const [status, setStatus] = useState<Status | null>(null);
  const [mode, setMode] = useState<Mode>('status');
  const [enrolment, setEnrolment] = useState<Enrolment | null>(null);
  const [newCodes, setNewCodes] = useState<string[]>([]);
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const refresh = useCallback(async () => {
    try {
      setStatus((await authService.totpStatus()).data);
    } catch {
      setError('No se pudo consultar el estado de la verificación en dos pasos.');
    }
  }, []);

  // Initial load: state is set from the promise callbacks, never synchronously in the effect.
  useEffect(() => {
    let alive = true;
    authService
      .totpStatus()
      .then((response) => alive && setStatus(response.data))
      .catch(
        () => alive && setError('No se pudo consultar el estado de la verificación en dos pasos.'),
      );
    return () => {
      alive = false;
    };
  }, []);

  const run = async (action: () => Promise<void>) => {
    setLoading(true);
    setError(null);
    try {
      await action();
    } catch (err) {
      setError(codeError(err));
    } finally {
      setLoading(false);
    }
  };

  const begin = () =>
    run(async () => {
      setEnrolment((await authService.totpBegin()).data);
      setMode('confirm-enrol');
    });

  const confirm = () =>
    run(async () => {
      await authService.totpConfirm(code.trim());
      toast('Verificación en dos pasos activada.', 'success');
      setCode('');
      setMode('status');
      setEnrolment(null);
      await refresh();
    });

  const disable = () =>
    run(async () => {
      await authService.totpDisable(code.trim());
      toast('Verificación en dos pasos desactivada.', 'success');
      setCode('');
      setMode('status');
      await refresh();
    });

  const regenerate = () =>
    run(async () => {
      setNewCodes((await authService.totpRecoveryCodes(code.trim())).data.recoveryCodes);
      setCode('');
      setMode('show-codes');
      await refresh();
    });

  const codeField = (label: string) => (
    <label className="two-factor__field">
      <span>{label}</span>
      <TextField
        value={code}
        autoComplete="one-time-code"
        aria-label={label}
        disabled={loading}
        onChange={(event) => {
          setCode(event.target.value);
          setError(null);
        }}
      />
    </label>
  );

  const codesList = (codes: string[]) => (
    <>
      <p>
        <strong>Guarda tus códigos de recuperación.</strong> Cada uno sirve una sola vez si pierdes
        tu teléfono, y no volverán a mostrarse.
      </p>
      <div className="two-factor__actions">
        <Button
          variant="secondary"
          size="sm"
          icon="ph ph-download-simple"
          onClick={() => {
            const file = recoveryCodesFile({
              username: user?.username ?? '',
              codes,
              issuedAt: new Date(),
            });
            downloadBlob(
              new Blob([file.content], { type: 'text/plain;charset=utf-8' }),
              file.filename,
            );
            toast('Códigos descargados.', 'success');
          }}
        >
          Descargar (.txt)
        </Button>
        <Button
          variant="ghost"
          size="sm"
          icon="ph ph-copy"
          onClick={() => {
            void navigator.clipboard?.writeText(codes.join('\n'));
            toast('Códigos copiados.', 'success');
          }}
        >
          Copiar
        </Button>
      </div>
      <details className="two-factor__details">
        <summary>Ver códigos</summary>
        <ul className="two-factor__codes" data-testid="two-factor-recovery-codes">
          {codes.map((value) => (
            <li key={value}>{value}</li>
          ))}
        </ul>
      </details>
    </>
  );

  let body: React.ReactNode;
  if (!status) {
    body = <p role="status">Cargando…</p>;
  } else if (mode === 'confirm-enrol' && enrolment) {
    body = (
      <>
        <ol className="two-factor__steps">
          <li>
            Escanea este código con tu app de autenticación (Google Authenticator, Authy,
            1Password…):
            <QrCode
              className="two-factor__qr"
              size={160}
              value={enrolment.uri}
              label="Código QR para agregar esta cuenta a tu app de autenticación"
            />
            <span className="two-factor__alt">
              ¿No puedes escanearlo? Ingresa esta clave a mano:
            </span>
            <code className="two-factor__secret" data-testid="two-factor-secret">
              {groupSecret(enrolment.secret)}
            </code>
          </li>
          <li>{codesList(enrolment.recoveryCodes)}</li>
          <li>Escribe el código de 6 dígitos que muestra la app para activarlo.</li>
        </ol>
        {codeField('Código de 6 dígitos')}
      </>
    );
  } else if (mode === 'disable') {
    body = (
      <>
        <p>
          Para desactivarla escribe un código de tu app o uno de tus códigos de recuperación. Así,
          nadie con tu sesión abierta puede quitarla.
        </p>
        {codeField('Código de verificación')}
      </>
    );
  } else if (mode === 'new-codes') {
    body = (
      <>
        <p>Los códigos anteriores dejarán de funcionar. Confirma con un código de tu app.</p>
        {codeField('Código de verificación')}
      </>
    );
  } else if (mode === 'show-codes') {
    body = codesList(newCodes);
  } else if (status.enrolled) {
    body = (
      <>
        <p role="status" data-testid="two-factor-enabled">
          <i className="ph ph-shield-check" aria-hidden="true" /> La verificación en dos pasos está{' '}
          <strong>activada</strong>.
        </p>
        <p>Códigos de recuperación disponibles: {status.recoveryCodesLeft}.</p>
      </>
    );
  } else {
    body = (
      <>
        <p>
          Protege tu cuenta con un segundo paso: además de la contraseña, se pedirá un código de tu
          teléfono al ingresar.
        </p>
        <p>
          Si pierdes el teléfono y los códigos de recuperación, un administrador puede
          restablecerla.
        </p>
      </>
    );
  }

  let footer: React.ReactNode;
  if (!status) {
    footer = <Button onClick={onClose}>Cerrar</Button>;
  } else if (mode === 'confirm-enrol') {
    footer = (
      <>
        <Button variant="secondary" disabled={loading} onClick={() => setMode('status')}>
          Cancelar
        </Button>
        <Button variant="primary" loading={loading} disabled={!code.trim()} onClick={confirm}>
          Activar
        </Button>
      </>
    );
  } else if (mode === 'disable' || mode === 'new-codes') {
    footer = (
      <>
        <Button variant="secondary" disabled={loading} onClick={() => setMode('status')}>
          Atrás
        </Button>
        <Button
          variant="primary"
          loading={loading}
          disabled={!code.trim()}
          onClick={mode === 'disable' ? disable : regenerate}
        >
          {mode === 'disable' ? 'Desactivar' : 'Generar nuevos'}
        </Button>
      </>
    );
  } else if (mode === 'show-codes') {
    footer = <Button onClick={() => setMode('status')}>Listo</Button>;
  } else if (status.enrolled) {
    footer = (
      <>
        <Button variant="secondary" onClick={() => setMode('new-codes')}>
          Nuevos códigos de recuperación
        </Button>
        <Button variant="secondary" onClick={() => setMode('disable')}>
          Desactivar
        </Button>
        <Button variant="primary" onClick={onClose}>
          Cerrar
        </Button>
      </>
    );
  } else {
    footer = (
      <>
        <Button variant="secondary" onClick={onClose}>
          Cancelar
        </Button>
        <Button variant="primary" loading={loading} onClick={begin}>
          Activar
        </Button>
      </>
    );
  }

  return (
    <AppDialog
      title="Verificación en dos pasos"
      visible={visible}
      width={480}
      height="auto"
      onClose={onClose}
      className="two-factor-modal"
      footer={footer}
    >
      <div className="two-factor">
        {body}
        {error && (
          <div className="two-factor__error" role="alert">
            {error}
          </div>
        )}
      </div>
    </AppDialog>
  );
};

/** Mounted only while open, so every opening starts from a clean state. */
export const TwoFactorModal = (props: TwoFactorModalProps) =>
  props.visible ? <TwoFactorDialog {...props} /> : null;
