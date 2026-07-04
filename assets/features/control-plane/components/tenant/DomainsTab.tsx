import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import type { TenantDomain } from '../../types';

interface DomainsTabProps {
  tenantId: number;
  domains: TenantDomain[];
  isAdmin: boolean;
  onUpdated: () => void;
}

export const DomainsTab = ({ tenantId, domains, isAdmin, onUpdated }: DomainsTabProps) => {
  const [domain, setDomain] = useState('');
  const [isPrimary, setIsPrimary] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const add = useMutation({
    mutationFn: () => controlPlaneClient.addDomain(tenantId, domain, isPrimary),
    onSuccess: () => {
      setDomain('');
      setIsPrimary(false);
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const setPrimary = useMutation({
    mutationFn: (domainId: number) => controlPlaneClient.setPrimaryDomain(tenantId, domainId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  const remove = useMutation({
    mutationFn: (domainId: number) => controlPlaneClient.deleteDomain(tenantId, domainId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div className="cp-grid cp-grid--2">
      {error && <div className="cp-alert cp-alert--danger cp-span-full">{error}</div>}

      {isAdmin && (
        <section className="cp-card">
          <h3>Add domain</h3>
          <form
            className="cp-form"
            onSubmit={(e) => {
              e.preventDefault();
              add.mutate();
            }}
          >
            <label className="cp-field">
              <span>Domain</span>
              <input
                value={domain}
                onChange={(e) => setDomain(e.target.value)}
                placeholder="acme.example.com"
                required
              />
            </label>
            <label className="cp-check">
              <input
                type="checkbox"
                checked={isPrimary}
                onChange={(e) => setIsPrimary(e.target.checked)}
              />
              Set as primary
            </label>
            <button type="submit" className="cp-btn cp-btn--primary" disabled={add.isPending}>
              Add domain
            </button>
          </form>
        </section>
      )}

      <section className={`cp-card${isAdmin ? '' : ' cp-span-full'}`}>
        <h3>Current domains</h3>
        {domains.length === 0 ? (
          <div className="cp-empty">No domains yet.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>Domain</th>
                <th>Primary</th>
                {isAdmin && <th />}
              </tr>
            </thead>
            <tbody>
              {domains.map((row) => {
                const primary = Boolean(row.is_primary);
                return (
                  <tr key={row.id}>
                    <td>
                      <code>{row.domain}</code>
                    </td>
                    <td>{primary ? 'Yes' : 'No'}</td>
                    {isAdmin && (
                      <td className="cp-table__actions">
                        {!primary && (
                          <button
                            type="button"
                            className="cp-btn cp-btn--ghost"
                            onClick={() => setPrimary.mutate(row.id)}
                          >
                            Set primary
                          </button>
                        )}
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          onClick={() => {
                            if (window.confirm(`Delete domain ${row.domain}?`)) {
                              remove.mutate(row.id);
                            }
                          }}
                        >
                          Delete
                        </button>
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};