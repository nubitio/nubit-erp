import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Badge, Button, Card, FileDropzone, FormField, Text, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { useUploadTenantCertificate, getTenantCertificate } from '@shared/hooks';

const syncLabel: Record<string, string> = {
  synced: 'Sincronizado con Lycet',
  upsert_pending: 'Pendiente de sincronización',
  upsert_failed: 'Error de sincronización',
};

export const SunatCertificateCard = () => {
  const certificate = useQuery({
    queryKey: ['tenant', 'certificate'],
    queryFn: getTenantCertificate,
  });
  const upload = useUploadTenantCertificate();
  const [solUser, setSolUser] = useState('');
  const [solPass, setSolPass] = useState('');
  const [certificateFile, setCertificateFile] = useState<File | null>(null);
  const [certificatePassword, setCertificatePassword] = useState('');

  const current = certificate.data?.certificate;
  const canSubmit = solUser.trim() !== '' && solPass !== '' && certificateFile !== null;
  const handleUpload = () => {
    if (!certificateFile) return;

    upload.mutate(
      { solUser: solUser.trim(), solPass, certificate: certificateFile, certificatePassword },
      {
        onSuccess: () => {
          setSolPass('');
          setCertificatePassword('');
          setCertificateFile(null);
          certificate.refetch();
          toast('Certificado SUNAT actualizado', 'success');
        },
        onError: (error) =>
          toast(error.message || 'No se pudo actualizar el certificado SUNAT', 'error'),
      },
    );
  };

  return (
    <Card
      title="Certificado SUNAT"
      description="Firma tus comprobantes y sincroniza las credenciales SOL con Lycet. El RUC de tu empresa se agrega automáticamente al usuario SOL."
    >
      {current ? (
        <div className="sunat-certificate__status">
          <div>
            <strong>RUC {current.ruc}</strong>
            <Text tone="muted">
              Vence el {new Date(current.expiresAt).toLocaleDateString('es-PE')}
            </Text>
          </div>
          <Badge variant={current.lycetSyncStatus === 'synced' ? 'success' : 'warning'}>
            {syncLabel[current.lycetSyncStatus] ?? current.lycetSyncStatus}
          </Badge>
        </div>
      ) : null}

      <div className="sunat-certificate__form">
        <FormField label="Usuario SOL (sin RUC)">
          <TextField
            value={solUser}
            onChange={(event) => setSolUser(event.target.value)}
            placeholder="Ej. MODDATOS"
          />
        </FormField>
        <FormField label="Clave SOL">
          <TextField
            value={solPass}
            onChange={(event) => setSolPass(event.target.value)}
            type="password"
          />
        </FormField>
        <FormField label="Certificado digital (.pfx o PEM)">
          <FileDropzone
            accept=".pfx,.pem,.crt,.cer"
            value={certificateFile ? { fileName: certificateFile.name } : null}
            labels={{
              prompt: 'Suelta el certificado o haz clic para buscarlo',
              hint: 'Acepta archivos PFX o PEM.',
              replace: 'Cambiar certificado',
              remove: 'Quitar certificado',
              open: 'Abrir certificado',
            }}
            onFileSelect={setCertificateFile}
            onClear={() => setCertificateFile(null)}
          />
        </FormField>
        <FormField label="Contraseña del certificado (solo PFX)">
          <TextField
            value={certificatePassword}
            onChange={(event) => setCertificatePassword(event.target.value)}
            type="password"
          />
        </FormField>
      </div>
      <div className="sunat-certificate__actions">
        <Button
          variant="primary"
          loading={upload.isPending}
          disabled={!canSubmit}
          onClick={handleUpload}
        >
          {current ? 'Reemplazar certificado' : 'Configurar certificado'}
        </Button>
      </div>
    </Card>
  );
};
