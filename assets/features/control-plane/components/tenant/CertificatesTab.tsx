import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { CertificateRecord } from '../../types';

interface CertificatesTabProps {
  tenantId: number;
  certificates: CertificateRecord[];
  isAdmin: boolean;
  onUpdated: () => void;
  initialCertificateId?: number | null;
  onCertificateModalClose?: () => void;
}

const statusClass = (status: string) => {
  if (status === 'active') return 'cp-badge--active';
  if (status === 'expiring_soon') return 'cp-badge--suspended';
  if (status === 'expired') return 'cp-badge--deleted';
  return '';
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

const field = (cert: CertificateRecord, camel: keyof CertificateRecord, snake: keyof CertificateRecord) =>
  (cert[camel] ?? cert[snake]) as string | number | null | undefined;

const CertificateDetailModal = ({
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
    <div className="cp-modal-backdrop" onClick={onClose}>
      <div className="cp-modal cp-modal--wide" onClick={(e) => e.stopPropagation()}>
        <header className="cp-modal__header">
          <h3>{cert ? `Certificado ${cert.ruc}` : 'Detalle del certificado'}</h3>
          <button type="button" className="cp-btn cp-btn--ghost" onClick={onClose}>
            {CP_STRINGS.close}
          </button>
        </header>
        <div className="cp-modal__body">
          {detail.isLoading && <div className="cp-muted">{CP_STRINGS.loading}</div>}
          {detail.error && (
            <div className="cp-alert cp-alert--danger">{(detail.error as Error).message}</div>
          )}
          {cert && (
            <>
              <p className="cp-muted">Operational metadata only. PEM material is not exposed.</p>
              <dl className="cp-dl">
                <dt>Status</dt>
                <dd>
                  <span className={`cp-badge ${statusClass(status)}`}>{status}</span>
                </dd>
                <dt>Subject CN</dt>
                <dd>{field(cert, 'subjectCn', 'subject_cn') ?? '—'}</dd>
                <dt>Issuer CN</dt>
                <dd>{field(cert, 'issuerCn', 'issuer_cn') ?? '—'}</dd>
                <dt>Serial</dt>
                <dd>
                  <code>{field(cert, 'serialNumber', 'serial_number') ?? '—'}</code>
                </dd>
                <dt>Fingerprint SHA-256</dt>
                <dd>
                  <code>{field(cert, 'fingerprintSha256', 'fingerprint_sha256') ?? '—'}</code>
                </dd>
                <dt>Valid from</dt>
                <dd>{formatDateTime(field(cert, 'validFrom', 'valid_from') as string | null)}</dd>
                <dt>Expires at</dt>
                <dd>{formatDateTime(field(cert, 'expiresAt', 'expires_at') as string | null)}</dd>
                <dt>Uploaded by</dt>
                <dd>
                  {field(cert, 'uploadedBy', 'uploaded_by') ?? '—'} at{' '}
                  {formatDateTime(field(cert, 'uploadedAt', 'uploaded_at') as string | null)}
                </dd>
                <dt>Revocation</dt>
                <dd>
                  {field(cert, 'revokedAt', 'revoked_at') ? (
                    <>
                      {field(cert, 'revokedBy', 'revoked_by') ?? '—'} at{' '}
                      {formatDateTime(field(cert, 'revokedAt', 'revoked_at') as string | null)}
                      <br />
                      <span className="cp-muted">
                        {field(cert, 'revocationReason', 'revocation_reason') ?? 'No reason recorded'}
                      </span>
                    </>
                  ) : (
                    '—'
                  )}
                </dd>
                <dt>Superseded by</dt>
                <dd>{field(cert, 'supersededById', 'superseded_by_id') ?? '—'}</dd>
                <dt>Last alerted</dt>
                <dd>{formatDateTime(field(cert, 'lastAlertedAt', 'last_alerted_at') as string | null)}</dd>
                <dt>Lycet sync</dt>
                <dd>
                  {field(cert, 'lycetSyncStatus', 'sync_status') ?? '—'}
                  {field(cert, 'lycetSyncError', 'lycet_sync_error') && (
                    <span className="cp-muted"> — {field(cert, 'lycetSyncError', 'lycet_sync_error')}</span>
                  )}
                </dd>
              </dl>
            </>
          )}
        </div>
      </div>
    </div>
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
  const fileRef = useRef<HTMLInputElement>(null);
  const [ruc, setRuc] = useState('');
  const [solUser, setSolUser] = useState('');
  const [solPass, setSolPass] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [selectedCertificateId, setSelectedCertificateId] = useState<number | null>(
    initialCertificateId,
  );

  useEffect(() => {
    if (initialCertificateId) {
      setSelectedCertificateId(initialCertificateId);
    }
  }, [initialCertificateId]);

  const closeDetail = () => {
    setSelectedCertificateId(null);
    onCertificateModalClose?.();
  };

  const revoke = useMutation({
    mutationFn: ({ ruc, reason }: { ruc: string; reason: string }) =>
      controlPlaneClient.revokeCertificate(tenantId, ruc, reason),
    onSuccess: () => {
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const upload = useMutation({
    mutationFn: () => {
      const file = fileRef.current?.files?.[0];
      if (!file) throw new Error('PEM certificate file is required');

      const formData = new FormData();
      formData.append('ruc', ruc);
      formData.append('solUser', solUser);
      formData.append('solPass', solPass);
      formData.append('certificate', file);

      return controlPlaneClient.uploadCertificate(tenantId, formData);
    },
    onSuccess: () => {
      setRuc('');
      setSolUser('');
      setSolPass('');
      if (fileRef.current) fileRef.current.value = '';
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div className="cp-grid cp-grid--2">
      {error && <div className="cp-alert cp-alert--danger cp-span-full">{error}</div>}

      {isAdmin && (
        <section className="cp-card">
          <h3>Upload / renew certificate</h3>
          <form
            className="cp-form"
            onSubmit={(e) => {
              e.preventDefault();
              upload.mutate();
            }}
          >
            <label className="cp-field">
              <span>RUC</span>
              <input
                value={ruc}
                onChange={(e) => setRuc(e.target.value)}
                maxLength={11}
                required
              />
            </label>
            <label className="cp-field">
              <span>SOL user</span>
              <input value={solUser} onChange={(e) => setSolUser(e.target.value)} required />
            </label>
            <label className="cp-field">
              <span>SOL password</span>
              <input
                type="password"
                value={solPass}
                onChange={(e) => setSolPass(e.target.value)}
                required
              />
            </label>
            <label className="cp-field">
              <span>PEM certificate</span>
              <input ref={fileRef} type="file" accept=".pem,.crt" required />
              <span className="cp-muted">
                The PEM is sent to lycet; only metadata is stored in Control Plane.
              </span>
            </label>
            <button type="submit" className="cp-btn cp-btn--primary" disabled={upload.isPending}>
              Upload / renew
            </button>
          </form>
        </section>
      )}

      <section className={`cp-card${isAdmin ? '' : ' cp-span-full'}`}>
        <h3>Registered certificates</h3>
        {certificates.length === 0 ? (
          <div className="cp-empty">No certificates registered.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>RUC</th>
                <th>Status</th>
                <th>Expires</th>
                <th>Fingerprint</th>
                <th>Sync</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {certificates.map((cert) => {
                const status = resolveStatus(cert);
                const revocable = isAdmin && ['active', 'expiring_soon', 'expired'].includes(status);
                const fingerprint = field(cert, 'fingerprintSha256', 'fingerprint_sha256');
                return (
                  <tr key={cert.id}>
                    <td className="cp-table__strong">{cert.ruc}</td>
                    <td>
                      <span className={`cp-badge ${statusClass(status)}`}>{status}</span>
                    </td>
                    <td>{formatDate(field(cert, 'expiresAt', 'expires_at') as string | null)}</td>
                    <td>
                      <code>{fingerprint ? String(fingerprint).slice(0, 12) : '—'}</code>
                    </td>
                    <td className="cp-muted">
                      {field(cert, 'lycetSyncStatus', 'sync_status') ?? '—'}
                    </td>
                    <td className="cp-table__actions">
                      <button
                        type="button"
                        className="cp-btn cp-btn--ghost"
                        onClick={() => setSelectedCertificateId(cert.id)}
                      >
                        {CP_STRINGS.view}
                      </button>
                      {revocable && (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          disabled={revoke.isPending}
                          onClick={() => {
                            const reason = window.prompt(`Revocation reason for ${cert.ruc}:`);
                            if (reason?.trim()) {
                              revoke.mutate({ ruc: cert.ruc, reason: reason.trim() });
                            }
                          }}
                        >
                          Revoke
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>

      {selectedCertificateId !== null && (
        <CertificateDetailModal
          tenantId={tenantId}
          certificateId={selectedCertificateId}
          onClose={closeDetail}
        />
      )}
    </div>
  );
};