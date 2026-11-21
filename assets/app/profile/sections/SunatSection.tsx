import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Badge, Button, FileDropzone, Skeleton, TextField } from '@nubitio/ui';
import { tenantSettingsService, type SunatStateDto } from '@features/config';
import { getTenantCertificate, useUploadTenantCertificate } from '@shared/hooks';
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

  if (certificate.isError) {
    return <Alert tone="warning">No se pudo cargar el certificado SUNAT.</Alert>;
  }
  if (certificate.isPending) return <Skeleton variant="rect" height={260} />;

  const current = certificate.data.certificate;
  const canSubmit = solUser.trim() !== '' && solPass !== '' && file !== null;

  const submit = () => {
    if (!file) return;
    upload.mutate(
      { solUser: solUser.trim(), solPass, certificate: file, certificatePassword: filePassword },
      {
        onSuccess: () => {
          setSolPass('');
          setFilePassword('');
          setFile(null);
          void queryClient.invalidateQueries({ queryKey: CERTIFICATE_KEY });
          toast('Certificado SUNAT actualizado', 'success');
        },
        onError: (error) =>
          toast(error.message || 'No se pudo actualizar el certificado SUNAT', 'error'),
      },
    );
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
        description="Sin el RUC: se agrega automáticamente."
        htmlFor="sunat-sol-user"
      >
        <TextField
          id="sunat-sol-user"
          value={solUser}
          placeholder="Ej. MODDATOS"
          autoComplete="off"
          onChange={(event) => setSolUser(event.target.value)}
        />
      </SettingsRow>
      <SettingsRow label="Clave SOL" htmlFor="sunat-sol-pass">
        <TextField
          id="sunat-sol-pass"
          type="password"
          value={solPass}
          autoComplete="new-password"
          onChange={(event) => setSolPass(event.target.value)}
        />
      </SettingsRow>
      <SettingsRow
        label="Certificado digital"
        description="Firma tus comprobantes. Archivo .pfx o PEM."
      >
        <div className="settings-dropzone">
          <FileDropzone
            accept=".pfx,.pem,.crt,.cer"
            value={file ? { fileName: file.name } : null}
            labels={{
              prompt: 'Suelta el certificado o haz clic para buscarlo',
              hint: 'Acepta archivos PFX o PEM.',
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
        description="Solo si el certificado es PFX."
        htmlFor="sunat-cert-pass"
      >
        <TextField
          id="sunat-cert-pass"
          type="password"
          value={filePassword}
          autoComplete="new-password"
          onChange={(event) => setFilePassword(event.target.value)}
        />
      </SettingsRow>
      <div className="settings-actions">
        <Button
          type="button"
          variant="primary"
          loading={upload.isPending}
          disabled={!canSubmit}
          onClick={submit}
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
