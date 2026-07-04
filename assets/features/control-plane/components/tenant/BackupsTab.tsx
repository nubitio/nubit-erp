import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { AppDropdown, Button, Card, EmptyState, FormField } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { BackupRecord } from '../../types';
import { formatBytes } from '../../utils/format';
import { RowActions, useConfirm } from '@nubitio/ui';
import { CpAlert, CpForm, CpFormActions, CpMetricGrid } from '../layout';

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

const S = CP_STRINGS.tenantTabs.backups;

const FREQUENCY_OPTIONS = [0, 6, 12, 24, 168, 720] as const;
const RETENTION_OPTIONS = [0, 7, 14, 30, 60, 90, 180, 365] as const;

export const BackupsTab = ({
  tenantId,
  tenant,
  backups,
  binlogSummary,
  isAdmin,
  isSuperAdmin,
  onUpdated,
}: BackupsTabProps) => {
  const { ask, ConfirmHost } = useConfirm();
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
      {error && <CpAlert tone="danger">{error}</CpAlert>}

      {isAdmin && (
        <Card variant="panel" title={S.scheduleTitle}>
          <CpForm
            variant="grid"
            onSubmit={(e) => {
              e.preventDefault();
              saveSchedule.mutate();
            }}
          >
            <FormField label={S.frequency}>
              <AppDropdown
                value={String(frequency)}
                options={FREQUENCY_OPTIONS.map((value) => ({
                  value: String(value),
                  label: S.frequencyOptions[value],
                }))}
                onChange={(value) => setFrequency(Number(value))}
                variant="form"
                showFieldLabel={false}
              />
            </FormField>
            <FormField label={S.retention}>
              <AppDropdown
                value={String(retention)}
                options={RETENTION_OPTIONS.map((value) => ({
                  value: String(value),
                  label: S.retentionOptions[value],
                }))}
                onChange={(value) => setRetention(Number(value))}
                variant="form"
                showFieldLabel={false}
              />
            </FormField>
            <Button type="submit" loading={saveSchedule.isPending}>
              {S.saveSchedule}
            </Button>
          </CpForm>
        </Card>
      )}

      {binlogSummary.total_count !== undefined && (
        <Card variant="panel" title={S.binlogTitle}>
          <CpMetricGrid
            layout="strip"
            density="compact"
            items={[
              {
                label: S.lastBackup,
                value: binlogSummary.last_backup_at ?? CP_STRINGS.never,
              },
              { label: S.total, value: binlogSummary.total_count ?? 0 },
              { label: S.size, value: formatBytes(binlogSummary.total_size_bytes ?? 0) },
            ]}
          />
        </Card>
      )}

      <Card variant="panel" title={S.listTitle}>
        {isSuperAdmin && (
          <CpFormActions>
            <Button loading={queue.isPending} onClick={() => queue.mutate()}>
              {S.backupNow}
            </Button>
          </CpFormActions>
        )}
        {backups.length === 0 ? (
          <EmptyState icon="archive" title={S.empty} size="sm" />
        ) : (
          <div className="cp-data-table-wrap">
            <table className="cp-data-table">
              <thead>
                <tr>
                  <th>{CP_STRINGS.tenants.colId}</th>
                  <th>{S.colType}</th>
                  <th>{S.colFile}</th>
                  <th>{S.colSize}</th>
                  <th>{CP_STRINGS.tenants.colStatus}</th>
                  <th>{CP_STRINGS.users.colCreated}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {backups.map((backup) => (
                  <tr key={backup.id}>
                    <td>{backup.id}</td>
                    <td>{backup.backup_type ?? 'full'}</td>
                    <td>
                      <code className="cp-code">{backup.filename}</code>
                    </td>
                    <td>{formatBytes(backup.size_bytes)}</td>
                    <td>{backup.status}</td>
                    <td>{backup.created_at}</td>
                    <td className="cp-data-table__actions">
                      <RowActions
                        items={[
                          ...(backup.status === 'completed'
                            ? [
                                {
                                  label: S.download,
                                  icon: 'download-simple',
                                  onClick: () => {
                                    window.location.href = controlPlaneClient.backupDownloadUrl(
                                      tenantId,
                                      backup.id,
                                    );
                                  },
                                },
                              ]
                            : []),
                          ...(isSuperAdmin && backup.status === 'completed'
                            ? [
                                {
                                  label: S.restore,
                                  icon: 'arrow-counter-clockwise',
                                  onClick: () =>
                                    ask({
                                      title: S.restoreConfirmTitle,
                                      message: S.restoreConfirm(backup.id),
                                      variant: 'warning',
                                      onConfirm: () => restore.mutate(backup.id),
                                    }),
                                },
                              ]
                            : []),
                          ...(isSuperAdmin
                            ? [
                                {
                                  separator: true,
                                  label: CP_STRINGS.delete,
                                  icon: 'trash',
                                  danger: true,
                                  onClick: () =>
                                    ask({
                                      title: S.deleteConfirmTitle,
                                      message: S.deleteConfirm(backup.id),
                                      variant: 'danger',
                                      onConfirm: () => remove.mutate(backup.id),
                                    }),
                                },
                              ]
                            : []),
                        ]}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      <ConfirmHost />
    </div>
  );
};