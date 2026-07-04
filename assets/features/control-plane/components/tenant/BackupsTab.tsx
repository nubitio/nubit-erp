import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import type { BackupRecord } from '../../types';
import { formatBytes } from '../../utils/format';

interface BackupsTabProps {
  tenantId: number;
  tenant: { backup_frequency?: number; backup_retention_days?: number };
  backups: BackupRecord[];
  binlogSummary: {
    last_backup_at?: string | null;
    total_count?: number;
    total_size_bytes?: number;
  };
  isAdmin: boolean;
  isSuperAdmin: boolean;
  onUpdated: () => void;
}

const FREQUENCY_OPTIONS = [
  { value: 0, label: 'Disabled' },
  { value: 6, label: 'Every 6 hours' },
  { value: 12, label: 'Every 12 hours' },
  { value: 24, label: 'Daily' },
  { value: 168, label: 'Weekly' },
  { value: 720, label: 'Monthly' },
];

const RETENTION_OPTIONS = [
  { value: 0, label: 'Forever' },
  { value: 7, label: '7 days' },
  { value: 14, label: '14 days' },
  { value: 30, label: '30 days' },
  { value: 60, label: '60 days' },
  { value: 90, label: '90 days' },
  { value: 180, label: '180 days' },
  { value: 365, label: '1 year' },
];

export const BackupsTab = ({
  tenantId,
  tenant,
  backups,
  binlogSummary,
  isAdmin,
  isSuperAdmin,
  onUpdated,
}: BackupsTabProps) => {
  const [frequency, setFrequency] = useState(tenant.backup_frequency ?? 0);
  const [retention, setRetention] = useState(tenant.backup_retention_days ?? 30);
  const [error, setError] = useState<string | null>(null);

  const saveSchedule = useMutation({
    mutationFn: () =>
      controlPlaneClient.updateBackupSchedule(tenantId, {
        backupFrequency: frequency,
        backupRetentionDays: retention,
      }),
    onSuccess: () => {
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const queue = useMutation({
    mutationFn: () => controlPlaneClient.queueBackup(tenantId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  const restore = useMutation({
    mutationFn: (backupId: number) => controlPlaneClient.restoreBackup(tenantId, backupId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  const remove = useMutation({
    mutationFn: (backupId: number) => controlPlaneClient.deleteBackup(tenantId, backupId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div className="cp-stack">
      {error && <div className="cp-alert cp-alert--danger">{error}</div>}

      {isAdmin && (
        <section className="cp-card">
          <h3>Backup schedule</h3>
          <form
            className="cp-form cp-form--grid"
            onSubmit={(e) => {
              e.preventDefault();
              saveSchedule.mutate();
            }}
          >
            <label className="cp-field">
              <span>Frequency</span>
              <select value={frequency} onChange={(e) => setFrequency(Number(e.target.value))}>
                {FREQUENCY_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="cp-field">
              <span>Retention</span>
              <select value={retention} onChange={(e) => setRetention(Number(e.target.value))}>
                {RETENTION_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </label>
            <button type="submit" className="cp-btn cp-btn--primary" disabled={saveSchedule.isPending}>
              Save schedule
            </button>
          </form>
        </section>
      )}

      {binlogSummary.total_count !== undefined && (
        <section className="cp-card">
          <h3>Binary log backups</h3>
          <div className="cp-metrics cp-metrics--compact">
            <div className="cp-metric">
              <span className="cp-metric__label">Last backup</span>
              <span className="cp-metric__value cp-metric__value--sm">
                {binlogSummary.last_backup_at ?? 'Never'}
              </span>
            </div>
            <div className="cp-metric">
              <span className="cp-metric__label">Total</span>
              <span className="cp-metric__value cp-metric__value--sm">
                {binlogSummary.total_count ?? 0}
              </span>
            </div>
            <div className="cp-metric">
              <span className="cp-metric__label">Size</span>
              <span className="cp-metric__value cp-metric__value--sm">
                {formatBytes(binlogSummary.total_size_bytes ?? 0)}
              </span>
            </div>
          </div>
        </section>
      )}

      <section className="cp-card">
        <div className="cp-card__header">
          <h3>Backups</h3>
          {isSuperAdmin && (
            <button
              type="button"
              className="cp-btn cp-btn--primary"
              disabled={queue.isPending}
              onClick={() => queue.mutate()}
            >
              Backup now
            </button>
          )}
        </div>
        {backups.length === 0 ? (
          <div className="cp-empty">No backups yet.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Type</th>
                <th>File</th>
                <th>Size</th>
                <th>Status</th>
                <th>Created</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {backups.map((backup) => (
                <tr key={backup.id}>
                  <td>{backup.id}</td>
                  <td>{backup.backup_type ?? 'full'}</td>
                  <td>
                    <code>{backup.filename}</code>
                  </td>
                  <td>{formatBytes(backup.size_bytes)}</td>
                  <td>{backup.status}</td>
                  <td>{backup.created_at}</td>
                  <td className="cp-table__actions">
                    {backup.status === 'completed' && (
                      <a
                        href={controlPlaneClient.backupDownloadUrl(tenantId, backup.id)}
                        className="cp-btn cp-btn--ghost"
                      >
                        Download
                      </a>
                    )}
                    {isSuperAdmin && backup.status === 'completed' && (
                      <button
                        type="button"
                        className="cp-btn cp-btn--ghost"
                        onClick={() => {
                          if (
                            window.confirm(
                              `Restore tenant from backup #${backup.id}? This overwrites the database.`,
                            )
                          ) {
                            restore.mutate(backup.id);
                          }
                        }}
                      >
                        Restore
                      </button>
                    )}
                    {isSuperAdmin && (
                      <button
                        type="button"
                        className="cp-btn cp-btn--ghost"
                        onClick={() => {
                          if (window.confirm(`Delete backup #${backup.id}?`)) {
                            remove.mutate(backup.id);
                          }
                        }}
                      >
                        Delete
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};