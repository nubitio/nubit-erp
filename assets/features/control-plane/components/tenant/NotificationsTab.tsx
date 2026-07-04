import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import type { NotificationChannelRow, TenantNotificationsPayload } from '../../types';

interface NotificationsTabProps {
  tenantId: number;
  isAdmin: boolean;
}

const SEVERITIES = ['critical', 'warning', 'info'] as const;

const cloneMatrix = (payload: TenantNotificationsPayload) =>
  JSON.parse(JSON.stringify(payload.matrix)) as TenantNotificationsPayload['matrix'];

export const NotificationsTab = ({ tenantId, isAdmin }: NotificationsTabProps) => {
  const [matrix, setMatrix] = useState<TenantNotificationsPayload['matrix'] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [testMessage, setTestMessage] = useState<string | null>(null);

  const notifications = useQuery({
    queryKey: ['control-plane', 'tenant', tenantId, 'notifications'],
    queryFn: () => controlPlaneClient.getTenantNotifications(tenantId),
  });

  useEffect(() => {
    if (notifications.data) {
      setMatrix(cloneMatrix(notifications.data));
    }
  }, [notifications.data]);

  const save = useMutation({
    mutationFn: () => controlPlaneClient.updateTenantNotifications(tenantId, matrix!),
    onSuccess: () => {
      setError(null);
      notifications.refetch();
    },
    onError: (err: Error) => setError(err.message),
  });

  const test = useMutation({
    mutationFn: ({ eventKey, channel }: { eventKey: string; channel: string }) =>
      controlPlaneClient.testTenantNotification(tenantId, eventKey, channel),
    onSuccess: (result) => setTestMessage(result.message),
    onError: (err: Error) => setError(err.message),
  });

  if (notifications.isLoading || !notifications.data) {
    return <div className="cp-page--loading">Loading notifications…</div>;
  }

  if (!matrix) {
    return <div className="cp-empty">No notification data.</div>;
  }

  const { eventDefinitions, channels } = notifications.data;

  const updateRow = (
    eventKey: string,
    channel: string,
    patch: Partial<NotificationChannelRow>,
  ) => {
    setMatrix((prev) => {
      if (!prev) return prev;
      const next = cloneMatrix({ ...notifications.data!, matrix: prev });
      next[eventKey][channel] = { ...next[eventKey][channel], ...patch };
      return next;
    });
  };

  return (
    <div className="cp-stack">
      <div className="cp-alert cp-alert--warning">
        Channels: email, bird (template), webhook. Recipients: comma or newline separated.
      </div>

      {error && <div className="cp-alert cp-alert--danger">{error}</div>}
      {testMessage && <div className="cp-alert cp-alert--info">{testMessage}</div>}

      {Object.entries(eventDefinitions).map(([eventKey, def]) => (
        <section key={eventKey} className="cp-card">
          <div className="cp-card__header">
            <h3>
              {def.label} <code>{eventKey}</code>
            </h3>
            <span className="cp-badge">{def.severity}</span>
          </div>
          <table className="cp-table">
            <thead>
              <tr>
                <th>Channel</th>
                <th>Enabled</th>
                <th>Severity</th>
                <th>Recipients</th>
                <th>Config JSON</th>
                {isAdmin && <th />}
              </tr>
            </thead>
            <tbody>
              {channels.map((channel) => {
                const row = matrix[eventKey][channel];
                return (
                  <tr key={channel}>
                    <td>
                      <code>{channel}</code>
                    </td>
                    <td>
                      <input
                        type="checkbox"
                        checked={row.enabled}
                        disabled={!isAdmin}
                        onChange={(e) =>
                          updateRow(eventKey, channel, { enabled: e.target.checked })
                        }
                      />
                    </td>
                    <td>
                      <select
                        value={row.severity}
                        disabled={!isAdmin}
                        onChange={(e) =>
                          updateRow(eventKey, channel, { severity: e.target.value })
                        }
                      >
                        {SEVERITIES.map((severity) => (
                          <option key={severity} value={severity}>
                            {severity}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td>
                      <textarea
                        className="cp-field__input"
                        rows={2}
                        disabled={!isAdmin}
                        value={row.recipients.join(', ')}
                        onChange={(e) =>
                          updateRow(eventKey, channel, {
                            recipients: e.target.value
                              .split(/[\n,;]+/)
                              .map((v) => v.trim())
                              .filter(Boolean),
                          })
                        }
                      />
                    </td>
                    <td>
                      <textarea
                        className="cp-field__input cp-field__input--mono"
                        rows={2}
                        disabled={!isAdmin}
                        value={
                          Object.keys(row.config).length > 0
                            ? JSON.stringify(row.config, null, 2)
                            : ''
                        }
                        onChange={(e) => {
                          const raw = e.target.value.trim();
                          if (raw === '') {
                            updateRow(eventKey, channel, { config: {} });
                            return;
                          }
                          try {
                            updateRow(eventKey, channel, {
                              config: JSON.parse(raw) as Record<string, unknown>,
                            });
                            setError(null);
                          } catch {
                            setError(`Invalid JSON config for ${eventKey} / ${channel}`);
                          }
                        }}
                      />
                    </td>
                    {isAdmin && (
                      <td>
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          disabled={test.isPending}
                          onClick={() => test.mutate({ eventKey, channel })}
                        >
                          Test
                        </button>
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </section>
      ))}

      {isAdmin && (
        <div className="cp-form__actions">
          <button
            type="button"
            className="cp-btn cp-btn--primary"
            disabled={save.isPending}
            onClick={() => save.mutate()}
          >
            Save notification settings
          </button>
        </div>
      )}
    </div>
  );
};