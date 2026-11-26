import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Badge, Button, FileDropzone, Skeleton, TextField } from '@nubitio/ui';
import { tenantSettingsService, type SunatStateDto } from '@features/config';
import { getTenantCertificate, useUploadTenantCertificate } from '@shared/hooks';
import {
  SUNAT_CERTIFICATE_ACCEPT,
  SunatCertificateConfirmation,
  SunatPasswordField,
  validateSunatCertificate,
} from '@shared/components/SunatCertificateConfirmation';
import toast from '@shared/lib/SharedUtils';
import { ToggleRow, ValueRow } from '../settings/SettingsControls';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';

const CERTIFICATE_KEY = ['tenant', 'certificate'] as const;
const SUNAT_STATE_KEY = ['tenant', 'sunat-state'] as const;

const SYNC_LABELS: Record<string, string> = {
  synced: 'Sincronizado con Lycet',
  upsert_pending: 'Pendiente de sincronización',
  upsert_failed: 'Error de sincronización',
};

/** "SUNAT": the certificate and SOL credentials that sign the receipts, and the sending rules. */
export const SunatSection = () => (
  <>
    <CertificateGroup />
    <SendingGroup />
  </>
);

const CertificateGroup = () => {
  const queryClient = useQueryClient();
  const certificate = useQuery({ queryKey: CERTIFICATE_KEY, queryFn: getTenantCertificate });
  const upload = useUploadTenantCertificate();
  const [solUser, setSolUser] = useState('');
  const [solPass, setSolPass] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [filePassword, setFilePassword] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [saveError, setSaveError] = useState<string>();
  const errors = submitted ? validateSunatCertificate(solUser, solPass, file) : undefined;

  if (certificate.isError) {
    return <Alert tone="warning">No se pudo cargar el certificado SUNAT.</Alert>;
  }
  if (certificate.isPending) return <Skeleton variant="rect" height={260} />;

  const current = certificate.data.certificate;
  const submit = () => {
    setSubmitted(true);
    setSaveError(undefined);
    if (!Object.values(validateSunatCertificate(solUser, solPass, file)).some(Boolean)) {
      setConfirming(true);
    }
  };

  const save = async (currentPassword: string) => {
    if (!file) return;
    const { certificate: saved } = await upload.mutateAsync({
      solUser: solUser.trim(),
      solPass,
      certificate: file,
      certificatePassword: filePassword,
      currentPassword,
    });
    upload.reset();
    setSolPass('');
    setFilePassword('');
    setFile(null);
    setSubmitted(false);
    void queryClient.invalidateQueries({ queryKey: CERTIFICATE_KEY });
    if (saved.lycetSyncStatus === 'synced') {
      toast('Certificado SUNAT actualizado', 'success');
    } else {
      toast('Certificado guardado. Revisa el estado de sincronización con Lycet.', 'warning');
    }
  };

  return (
    <SettingsGroup title="Certificado SUNAT">
      {current && (
        <SettingsRow
          label={`RUC ${current.ruc}`}
          description={`Vence el ${new Date(current.expiresAt).toLocaleDateString('es-PE')}`}
        >
          <Badge variant={current.lycetSyncStatus === 'synced' ? 'success' : 'warning'}>
            {SYNC_LABELS[current.lycetSyncStatus] ?? current.lycetSyncStatus}
          </Badge>
        </SettingsRow>
      )}
      <SettingsRow
        label="Usuario SOL"
        description={errors?.solUser ?? 'Sin el RUC: se agrega automáticamente.'}
        htmlFor="sunat-sol-user"
      >
        <TextField
          id="sunat-sol-user"
          invalid={Boolean(errors?.solUser)}
          value={solUser}
          placeholder="Ej. MODDATOS"
          autoComplete="off"
          onChange={(event) => setSolUser(event.target.value)}
        />
      </SettingsRow>
      <SettingsRow label="Clave SOL" description={errors?.solPass} htmlFor="sunat-sol-pass">
        <SunatPasswordField
          id="sunat-sol-pass"
          invalid={Boolean(errors?.solPass)}
          value={solPass}
          autoComplete="new-password"
          onChange={(event) => setSolPass(event.target.value)}
        />
      </SettingsRow>
      <SettingsRow
        label="Certificado digital"
        description="Firma tus comprobantes. Archivo .p12, .pfx o PEM."
      >
        <div className="settings-dropzone">
          <FileDropzone
            accept={SUNAT_CERTIFICATE_ACCEPT}
            error={errors?.certificate}
            invalid={Boolean(errors?.certificate)}
            value={file ? { fileName: file.name } : null}
            labels={{
              prompt: 'Suelta el certificado o haz clic para buscarlo',
              hint: 'Acepta archivos P12, PFX o PEM.',
              replace: 'Cambiar certificado',
              remove: 'Quitar certificado',
              open: 'Abrir certificado',
            }}
            onFileSelect={setFile}
            onClear={() => setFile(null)}
          />
        </div>
      </SettingsRow>
      <SettingsRow
        label="Contraseña del certificado"
        description="Para archivos P12 o PFX protegidos."
        htmlFor="sunat-cert-pass"
      >
        <SunatPasswordField
          id="sunat-cert-pass"
          value={filePassword}
          autoComplete="new-password"
          onChange={(event) => setFilePassword(event.target.value)}
        />
      </SettingsRow>
      {saveError && <Alert tone="danger">{saveError}</Alert>}
      {confirming && (
        <SunatCertificateConfirmation
          onClose={() => {
            upload.reset();
            setConfirming(false);
          }}
          onConfirm={save}
          onError={setSaveError}
        />
      )}
      <div className="settings-actions">
        <Button
          type="button"
          variant="primary"
          loading={upload.isPending}
          onClick={submit}
          data-testid="save-sunat-certificate"
        >
          {current ? 'Reemplazar certificado' : 'Configurar certificado'}
        </Button>
      </div>
    </SettingsGroup>
  );
};

const SendingGroup = () => {
  const queryClient = useQueryClient();
  const state = useQuery({
    queryKey: SUNAT_STATE_KEY,
    queryFn: () => tenantSettingsService.getSunatState(),
  });
  const patch = useMutation({
    mutationFn: (change: Partial<SunatStateDto>) => tenantSettingsService.patchSunatState(change),
    onSuccess: (updated, change) => {
      queryClient.setQueryData(SUNAT_STATE_KEY, updated);
      toast(
        'sendEnabled' in change
          ? updated.sendEnabled
            ? 'Envío a SUNAT activado'
            : 'Envío a SUNAT pausado'
          : 'Umbral actualizado',
        'success',
      );
    },
    onError: () => toast('No se pudo guardar el cambio', 'error'),
  });

  if (state.isError) return <Alert tone="warning">No se pudo cargar el estado de SUNAT.</Alert>;
  if (state.isPending) return <Skeleton variant="rect" height={180} />;

  const { sendEnabled, rejectionStreak, rejectionThreshold } = state.data;
  const streakVariant =
    rejectionStreak === 0
      ? 'secondary'
      : rejectionStreak >= rejectionThreshold
        ? 'danger'
        : 'warning';

  return (
    <SettingsGroup title="Envío electrónico">
      <ToggleRow
        label="Envío automático a SUNAT"
        description={
          sendEnabled
            ? 'Los comprobantes se envían automáticamente a SUNAT.'
            : 'El envío está pausado. Actívalo cuando el problema esté resuelto.'
        }
        checked={sendEnabled}
        disabled={patch.isPending}
        onChange={(checked) => patch.mutate({ sendEnabled: checked })}
      />
      <SettingsRow
        label="Rechazos consecutivos"
        description="El envío se pausa automáticamente al alcanzar el umbral."
      >
        <Badge variant={streakVariant}>
          {rejectionStreak} / {rejectionThreshold}
        </Badge>
      </SettingsRow>
      <ValueRow
        key={rejectionThreshold}
        id="sunat-threshold"
        label="Umbral de pausa automática"
        description="Número de rechazos consecutivos antes de pausar el envío."
        value={String(rejectionThreshold)}
        type="number"
        min={1}
        step={1}
        validate={(value) =>
          Number.isInteger(Number(value)) && Number(value) >= 1
            ? null
            : 'El umbral debe ser un número entero mayor a 0.'
        }
        onSave={async (value) => {
          await patch.mutateAsync({ rejectionThreshold: Number(value) });
        }}
      />
    </SettingsGroup>
  );
};
