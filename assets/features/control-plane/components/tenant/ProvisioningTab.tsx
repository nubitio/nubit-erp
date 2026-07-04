import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import type { ProvisionRecord } from '../../types';

interface ProvisioningTabProps {
  tenantId: number;
  primaryDomain: string | null;
  provisions: ProvisionRecord[];
  isAdmin: boolean;
  onUpdated: () => void;
}

const statusClass = (status: string) => {
  if (status === 'completed') return 'cp-badge--active';
  if (status === 'failed') return 'cp-badge--deleted';
  if (status === 'running') return 'cp-badge--info';
  return 'cp-badge--suspended';
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
      {error && <div className="cp-alert cp-alert--danger">{error}</div>}

      {isAdmin && (
        <section className="cp-card">
          <h3>Dispatch provisioning</h3>
          <form
            className="cp-form cp-form--grid"
            onSubmit={(e) => {
              e.preventDefault();
              provision.mutate();
            }}
          >
            <label className="cp-field">
              <span>Admin user</span>
              <input value={adminUser} onChange={(e) => setAdminUser(e.target.value)} required />
            </label>
            <label className="cp-field">
              <span>Admin password</span>
              <input
                type="password"
                value={adminPassword}
                onChange={(e) => setAdminPassword(e.target.value)}
                required
              />
            </label>
            <label className="cp-field">
              <span>Domain</span>
              <input value={domain} onChange={(e) => setDomain(e.target.value)} />
            </label>
            <button type="submit" className="cp-btn cp-btn--primary" disabled={provision.isPending}>
              Dispatch provisioning
            </button>
          </form>
        </section>
      )}

      <section className="cp-card">
        <h3>Provisioning history</h3>
        {provisions.length === 0 ? (
          <div className="cp-empty">No provisioning runs yet.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Domain</th>
                <th>Container</th>
                <th>Created</th>
                <th>Error</th>
              </tr>
            </thead>
            <tbody>
              {provisions.map((row) => (
                <tr key={row.id}>
                  <td>{row.id}</td>
                  <td>
                    <span className={`cp-badge ${statusClass(row.status)}`}>{row.status}</span>
                  </td>
                  <td>
                    <code>{row.domain}</code>
                  </td>
                  <td>{row.target_container ?? '—'}</td>
                  <td>{row.created_at}</td>
                  <td className="cp-muted">{row.error_message ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};