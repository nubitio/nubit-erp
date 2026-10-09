/* eslint-disable react-hooks/set-state-in-effect -- certificate routes hydrate the edit form */
import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import {
  Badge,
  Button,
  Card,
  Code,
  Drawer,
  EmptyState,
  FileDropzone,
  FormField,
  TextAreaField,
  TextField,
} from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { CertificateRecord } from '../../types';
import { CpAlert, CpDescriptionList, CpForm, CpFormActions, Col, DataTable, Grid } from '../layout';

interface CertificatesTabProps {
  tenantId: number;
  certificates: CertificateRecord[];
  isAdmin: boolean;
  onUpdated: () => void;
  initialCertificateId?: number | null;
  onCertificateModalClose?: () => void;
}

const S = CP_STRINGS.tenantTabs.certificates;

const statusVariant = (status: string): 'success' | 'warning' | 'danger' | 'secondary' => {
  if (status === 'active') return 'success';
  if (status === 'expiring_soon') return 'warning';
  if (status === 'expired') return 'danger';
  return 'secondary';
};

const resolveStatus = (cert: CertificateRecord): string => {
  if (typeof cert.status === 'string') return cert.status;
  if (cert.status && typeof cert.status === 'object' && 'value' in cert.status) {
    return String((cert.status as { value: string }).value);
  }
  return 'unknown';
};

const formatDateTime = (raw?: string | null): string => {
  if (!raw) return '—';
  return raw.slice(0, 16).replace('T', ' ');
};

const formatDate = (raw?: string | null): string => {
  if (!raw) return '—';
  return raw.slice(0, 10);
};

const field = (
  cert: CertificateRecord,
  camel: keyof CertificateRecord,
  snake: keyof CertificateRecord,
) => (cert[camel] ?? cert[snake]) as string | number | null | undefined;

const CertificateDetailDrawer = ({
  tenantId,
  certificateId,
  onClose,
}: {
  tenantId: number;
  certificateId: number;
  onClose: () => void;
}) => {
  const detail = useQuery({
    queryKey: ['control-plane', 'tenant', tenantId, 'certificate', certificateId],
    queryFn: () => controlPlaneClient.getCertificateDetail(tenantId, certificateId),
  });

  const cert = detail.data?.certificate;
  const status = cert ? resolveStatus(cert) : '';

  return (
    <Drawer
      isOpen
      onClose={onClose}
      title={cert ? S.detailTitle(cert.ruc) : S.detailLoading}
      width={640}
      scrim="overlay"
      closeLabel={CP_STRINGS.close}
    >
      {detail.isLoading && <p className="cp-muted">{CP_STRINGS.loading}</p>}
      {detail.error && <CpAlert tone="danger">{(detail.error as Error).message}</CpAlert>}
      {cert && (
        <>
          <p className="cp-muted">{S.detailHint}</p>
          <CpDescriptionList
            items={[
              {
                term: S.status,
                value: (
                  <Badge variant={statusVariant(status)} pill>
                    {status}
                  </Badge>
                ),
              },
              { term: S.subjectCn, value: field(cert, 'subjectCn', 'subject_cn') ?? '—' },
              { term: S.issuerCn, value: field(cert, 'issuerCn', 'issuer_cn') ?? '—' },
              {
                term: S.serial,
                value: <Code>{field(cert, 'serialNumber', 'serial_number') ?? '—'}</Code>,
              },
              {
                term: S.fingerprint,
                value: <Code>{field(cert, 'fingerprintSha256', 'fingerprint_sha256') ?? '—'}</Code>,
              },
              {
                term: S.validFrom,
                value: formatDateTime(field(cert, 'validFrom', 'valid_from') as string | null),
              },
              {
                term: S.expiresAt,
                value: formatDateTime(field(cert, 'expiresAt', 'expires_at') as string | null),
              },
              {
                term: S.uploadedBy,
                value: (
                  <>
                    {field(cert, 'uploadedBy', 'uploaded_by') ?? '—'} {S.uploadedAt}{' '}
                    {formatDateTime(field(cert, 'uploadedAt', 'uploaded_at') as string | null)}
                  </>
                ),
              },
              {
                term: S.revocation,
                value: field(cert, 'revokedAt', 'revoked_at') ? (
                  <>
                    {field(cert, 'revokedBy', 'revoked_by') ?? '—'} {S.uploadedAt}{' '}
                    {formatDateTime(field(cert, 'revokedAt', 'revoked_at') as string | null)}
                    <br />
                    <span className="cp-muted">
                      {field(cert, 'revocationReason', 'revocation_reason') ?? S.noReason}
                    </span>
                  </>
                ) : (
                  '—'
                ),
              },
              {
                term: S.supersededBy,
                value: field(cert, 'supersededById', 'superseded_by_id') ?? '—',
              },
              {
                term: S.lastAlerted,
                value: formatDateTime(
                  field(cert, 'lastAlertedAt', 'last_alerted_at') as string | null,
                ),
              },
              {
                term: S.lycetSync,
                value: (
                  <>
                    {field(cert, 'lycetSyncStatus', 'sync_status') ?? '—'}
                    {field(cert, 'lycetSyncError', 'lycet_sync_error') && (
                      <span className="cp-muted">
                        {' '}
                        — {field(cert, 'lycetSyncError', 'lycet_sync_error')}
                      </span>
                    )}
                  </>
                ),
              },
            ]}
          />
        </>
      )}
    </Drawer>
  );
};

export const CertificatesTab = ({
  tenantId,
  certificates,
  isAdmin,
  onUpdated,
  initialCertificateId = null,
  onCertificateModalClose,
}: CertificatesTabProps) => {
  const [ruc, setRuc] = useState('');
  const [solUser, setSolUser] = useState('');
  const [solPass, setSolPass] = useState('');
  const [pemFile, setPemFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [selectedCertificateId, setSelectedCertificateId] = useState<number | null>(
    initialCertificateId,
  );
  const [revokeTarget, setRevokeTarget] = useState<string | null>(null);
  const [revokeReason, setRevokeReason] = useState('');

  useEffect(() => {
    if (initialCertificateId) {
      setSelectedCertificateId(initialCertificateId);
    }
  }, [initialCertificateId]);

  const closeDetail = () => {
    setSelectedCertificateId(null);
    onCertificateModalClose?.();
  };

  const closeRevoke = () => {
    setRevokeTarget(null);
    setRevokeReason('');
  };

  const revoke = useMutation({
    mutationFn: ({ ruc, reason }: { ruc: string; reason: string }) =>
      controlPlaneClient.revokeCertificate(tenantId, ruc, reason),
    onSuccess: () => {
      setError(null);
      closeRevoke();
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const upload = useMutation({
    mutationFn: () => {
      if (!pemFile) throw new Error('PEM certificate file is required');

      const formData = new FormData();
      formData.append('ruc', ruc);
      formData.append('solUser', solUser);
      formData.append('solPass', solPass);
      formData.append('certificate', pemFile);

      return controlPlaneClient.uploadCertificate(tenantId, formData);
    },
    onSuccess: () => {
      setRuc('');
      setSolUser('');
      setSolPass('');
      setPemFile(null);
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <Grid cols={12} gap={4}>
      {error && (
        <Col fullWidth>
          <CpAlert tone="danger">{error}</CpAlert>
        </Col>
      )}

      {isAdmin && (
        <Col spanMd={4}>
          <Card variant="panel" title={S.uploadTitle}>
            <CpForm
              onSubmit={(e) => {
                e.preventDefault();
                upload.mutate();
              }}
            >
              <FormField label={S.ruc}>
                <TextField
                  value={ruc}
                  onChange={(e) => setRuc(e.target.value)}
                  maxLength={11}
                  required
                />
              </FormField>
              <FormField label={S.solUser}>
                <TextField value={solUser} onChange={(e) => setSolUser(e.target.value)} required />
              </FormField>
              <FormField label={S.solPassword}>
                <TextField
                  type="password"
                  value={solPass}
                  onChange={(e) => setSolPass(e.target.value)}
                  required
                />
              </FormField>
              <FormField label={S.pemFile} helpText={S.pemHint}>
                <FileDropzone
                  accept=".pem,.crt"
                  value={pemFile ? { fileName: pemFile.name } : null}
                  labels={{
                    prompt: S.dropzonePrompt,
                    hint: S.dropzoneHint,
                    replace: S.dropzoneReplace,
                    remove: S.dropzoneRemove,
                  }}
                  onFileSelect={setPemFile}
                  onClear={() => setPemFile(null)}
                />
              </FormField>
              <Button type="submit" loading={upload.isPending} fullWidth disabled={!pemFile}>
                {S.uploadSubmit}
              </Button>
            </CpForm>
          </Card>
        </Col>
      )}

      <Col spanMd={isAdmin ? 8 : 12}>
        <Card variant="panel" title={S.listTitle}>
          {certificates.length === 0 ? (
            <EmptyState icon="shield-check" title={S.empty} size="sm" />
          ) : (
            <DataTable
              variant="flush"
              rows={certificates}
              getRowKey={(cert) => cert.id}
              columns={[
                {
                  id: 'ruc',
                  header: S.ruc,
                  render: (cert) => <strong>{cert.ruc}</strong>,
                },
                {
                  id: 'status',
                  header: S.status,
                  render: (cert) => {
                    const status = resolveStatus(cert);
                    return (
                      <Badge variant={statusVariant(status)} pill>
                        {status}
                      </Badge>
                    );
                  },
                },
                {
                  id: 'expires',
                  header: S.colExpires,
                  accessor: (cert) =>
                    formatDate(field(cert, 'expiresAt', 'expires_at') as string | null),
                },
                {
                  id: 'fingerprint',
                  header: S.colFingerprint,
                  render: (cert) => {
                    const fingerprint = field(cert, 'fingerprintSha256', 'fingerprint_sha256');
                    return <Code>{fingerprint ? String(fingerprint).slice(0, 12) : '—'}</Code>;
                  },
                },
                {
                  id: 'sync',
                  header: S.colSync,
                  muted: true,
                  accessor: (cert) => field(cert, 'lycetSyncStatus', 'sync_status') ?? '—',
                },
              ]}
              rowActions={(cert) => {
                const status = resolveStatus(cert);
                const revocable =
                  isAdmin && ['active', 'expiring_soon', 'expired'].includes(status);
                return [
                  {
                    label: CP_STRINGS.view,
                    icon: 'eye' as const,
                    onClick: () => setSelectedCertificateId(cert.id),
                  },
                  ...(revocable
                    ? [
                        {
                          separator: true,
                          label: S.revoke,
                          icon: 'prohibit' as const,
                          danger: true,
                          onClick: () => {
                            setRevokeTarget(cert.ruc);
                            setRevokeReason('');
                          },
                        },
                      ]
                    : []),
                ];
              }}
            />
          )}
        </Card>
      </Col>

      {selectedCertificateId !== null && (
        <CertificateDetailDrawer
          tenantId={tenantId}
          certificateId={selectedCertificateId}
          onClose={closeDetail}
        />
      )}

      <Drawer
        isOpen={revokeTarget !== null}
        onClose={closeRevoke}
        title={revokeTarget ? S.revokeTitle(revokeTarget) : S.revoke}
        width={480}
        scrim="overlay"
        closeLabel={CP_STRINGS.close}
      >
        <CpForm
          onSubmit={(e) => {
            e.preventDefault();
            if (revokeTarget && revokeReason.trim()) {
              revoke.mutate({ ruc: revokeTarget, reason: revokeReason.trim() });
            }
          }}
        >
          <FormField label={S.revokeReason}>
            <TextAreaField
              value={revokeReason}
              onChange={(e) => setRevokeReason(e.target.value)}
              rows={4}
              required
            />
          </FormField>
          <CpFormActions>
            <Button type="submit" loading={revoke.isPending} disabled={!revokeReason.trim()}>
              {S.revokeSubmit}
            </Button>
            <Button type="button" variant="ghost" onClick={closeRevoke}>
              {CP_STRINGS.cancel}
            </Button>
          </CpFormActions>
        </CpForm>
      </Drawer>
    </Grid>
  );
};
