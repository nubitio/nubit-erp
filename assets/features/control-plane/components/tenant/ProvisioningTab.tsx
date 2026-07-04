import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Badge, Button, Card, EmptyState, FormField, TextField } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { ProvisionRecord } from '../../types';
import { CpAlert, CpForm } from '../layout';

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
    <div className="cp-stack">
      {error && <CpAlert tone="danger">{error}</CpAlert>}

      {isAdmin && (
        <Card title={S.dispatchTitle}>
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

      <Card title={S.historyTitle}>
        {provisions.length === 0 ? (
          <EmptyState icon="rocket-launch" title={S.empty} size="sm" />
        ) : (
          <div className="cp-data-table-wrap">
            <table className="cp-data-table">
              <thead>
                <tr>
                  <th>{CP_STRINGS.tenants.colId}</th>
                  <th>{S.colStatus}</th>
                  <th>{CP_STRINGS.tenants.colDomain}</th>
                  <th>{S.colContainer}</th>
                  <th>{S.colCreated}</th>
                  <th>{S.colError}</th>
                </tr>
              </thead>
              <tbody>
                {provisions.map((row) => (
                  <tr key={row.id}>
                    <td>{row.id}</td>
                    <td>
                      <Badge variant={statusVariant(row.status)} pill>
                        {row.status}
                      </Badge>
                    </td>
                    <td>
                      <code className="cp-code">{row.domain}</code>
                    </td>
                    <td>{row.target_container ?? '—'}</td>
                    <td>{row.created_at}</td>
                    <td className="cp-muted">{row.error_message ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
};