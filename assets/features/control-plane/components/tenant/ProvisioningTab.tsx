import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Badge, Button, Card, Code, EmptyState, FormField, TextField } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { ProvisionRecord } from '../../types';
import { CpAlert, CpForm, DataTable, Stack } from '../layout';

interface ProvisioningTabProps {
  tenantId: number;
  primaryDomain: string | null;
  provisions: ProvisionRecord[];
  isAdmin: boolean;
  onUpdated: () => void;
}

const S = CP_STRINGS.tenantTabs.provisioning;

const statusVariant = (status: string): 'success' | 'danger' | 'info' | 'secondary' => {
  if (status === 'completed') return 'success';
  if (status === 'failed') return 'danger';
  if (status === 'running') return 'info';
  return 'secondary';
};

export const ProvisioningTab = ({
  tenantId,
  primaryDomain,
  provisions,
  isAdmin,
  onUpdated,
}: ProvisioningTabProps) => {
  const [adminUser, setAdminUser] = useState('admin');
  const [adminPassword, setAdminPassword] = useState('');
  const [domain, setDomain] = useState(primaryDomain ?? '');
  const [error, setError] = useState<string | null>(null);

  const provision = useMutation({
    mutationFn: () =>
      controlPlaneClient.provisionTenant(tenantId, { adminUser, adminPassword, domain }),
    onSuccess: () => {
      setAdminPassword('');
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <Stack>
      {error && <CpAlert tone="danger">{error}</CpAlert>}

      {isAdmin && (
        <Card variant="panel" title={S.dispatchTitle}>
          <CpForm
            variant="grid"
            onSubmit={(e) => {
              e.preventDefault();
              provision.mutate();
            }}
          >
            <FormField label={S.adminUser}>
              <TextField value={adminUser} onChange={(e) => setAdminUser(e.target.value)} required />
            </FormField>
            <FormField label={S.adminPassword}>
              <TextField
                type="password"
                value={adminPassword}
                onChange={(e) => setAdminPassword(e.target.value)}
                required
              />
            </FormField>
            <FormField label={S.domain}>
              <TextField value={domain} onChange={(e) => setDomain(e.target.value)} />
            </FormField>
            <Button type="submit" loading={provision.isPending}>
              {S.dispatchSubmit}
            </Button>
          </CpForm>
        </Card>
      )}

      <Card variant="panel" title={S.historyTitle}>
        {provisions.length === 0 ? (
          <EmptyState icon="rocket-launch" title={S.empty} size="sm" />
        ) : (
          <DataTable
            variant="flush"
            rows={provisions}
            getRowKey={(row) => row.id}
            columns={[
              { id: 'id', header: CP_STRINGS.tenants.colId, accessor: (row) => row.id },
              {
                id: 'status',
                header: S.colStatus,
                render: (row) => (
                  <Badge variant={statusVariant(row.status)} pill>
                    {row.status}
                  </Badge>
                ),
              },
              {
                id: 'domain',
                header: CP_STRINGS.tenants.colDomain,
                render: (row) => <Code>{row.domain}</Code>,
              },
              {
                id: 'container',
                header: S.colContainer,
                accessor: (row) => row.target_container ?? '—',
              },
              { id: 'created', header: S.colCreated, accessor: (row) => row.created_at },
              {
                id: 'error',
                header: S.colError,
                muted: true,
                accessor: (row) => row.error_message ?? '—',
              },
            ]}
          />
        )}
      </Card>
    </Stack>
  );
};