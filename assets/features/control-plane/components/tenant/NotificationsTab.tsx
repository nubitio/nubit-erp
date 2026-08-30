/* eslint-disable react-hooks/set-state-in-effect -- loaded preferences hydrate local form state */
import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import {
  Badge,
  Button,
  Card,
  Code,
  EmptyState,
  SelectField,
  TextAreaField,
  Toggle,
} from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { NotificationChannelRow, TenantNotificationsPayload } from '../../types';
import { CpAlert, CpFormActions, DataTable, Stack } from '../layout';

interface NotificationsTabProps {
  tenantId: number;
  isAdmin: boolean;
}

const S = CP_STRINGS.tenantTabs.notifications;

const SEVERITIES = ['critical', 'warning', 'info'] as const;

const severityVariant = (severity: string): 'danger' | 'warning' | 'info' | 'secondary' => {
  if (severity === 'critical') return 'danger';
  if (severity === 'warning') return 'warning';
  if (severity === 'info') return 'info';
  return 'secondary';
};

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
    return <p className="cp-muted">{CP_STRINGS.loading}</p>;
  }

  if (!matrix) {
    return <EmptyState icon="bell" title={S.empty} size="sm" />;
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
    <Stack>
      <CpAlert tone="warning">{S.hint}</CpAlert>

      {error && <CpAlert tone="danger">{error}</CpAlert>}
      {testMessage && <CpAlert tone="info">{testMessage}</CpAlert>}

      {Object.entries(eventDefinitions).map(([eventKey, def]) => (
        <Card variant="panel" key={eventKey} title={def.label}>
          <div className="cp-card-header">
            <Code>{eventKey}</Code>
            <Badge variant={severityVariant(def.severity)} pill>
              {def.severity}
            </Badge>
          </div>
          <DataTable
            variant="flush"
            rows={channels}
            getRowKey={(channel) => channel}
            columns={[
              {
                id: 'channel',
                header: S.colChannel,
                render: (channel) => <Code>{channel}</Code>,
              },
              {
                id: 'enabled',
                header: S.colEnabled,
                render: (channel) => (
                  <Toggle
                    checked={matrix[eventKey][channel].enabled}
                    disabled={!isAdmin}
                    onChange={(checked) => updateRow(eventKey, channel, { enabled: checked })}
                    aria-label={`${S.colEnabled} ${channel}`}
                  />
                ),
              },
              {
                id: 'severity',
                header: S.colSeverity,
                render: (channel) => (
                  <SelectField
                    value={matrix[eventKey][channel].severity}
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
                  </SelectField>
                ),
              },
              {
                id: 'recipients',
                header: S.colRecipients,
                render: (channel) => (
                  <TextAreaField
                    rows={2}
                    disabled={!isAdmin}
                    value={matrix[eventKey][channel].recipients.join(', ')}
                    onChange={(e) =>
                      updateRow(eventKey, channel, {
                        recipients: e.target.value
                          .split(/[\n,;]+/)
                          .map((v) => v.trim())
                          .filter(Boolean),
                      })
                    }
                  />
                ),
              },
              {
                id: 'config',
                header: S.colConfig,
                render: (channel) => (
                  <TextAreaField
                    className="cp-field__input--mono"
                    rows={2}
                    disabled={!isAdmin}
                    value={
                      Object.keys(matrix[eventKey][channel].config).length > 0
                        ? JSON.stringify(matrix[eventKey][channel].config, null, 2)
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
                        setError(S.invalidJson(eventKey, channel));
                      }
                    }}
                  />
                ),
              },
            ]}
            renderActions={
              isAdmin
                ? (channel) => (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      loading={test.isPending}
                      onClick={() => test.mutate({ eventKey, channel })}
                    >
                      {S.test}
                    </Button>
                  )
                : undefined
            }
          />
        </Card>
      ))}

      {isAdmin && (
        <CpFormActions>
          <Button loading={save.isPending} onClick={() => save.mutate()}>
            {S.save}
          </Button>
        </CpFormActions>
      )}
    </Stack>
  );
};
