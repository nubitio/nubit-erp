import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import type { PlanOption, TenantRecord } from '../../types';
import { TenantStatusBadge } from '../TenantStatusBadge';

interface GeneralTabProps {
  tenant: TenantRecord;
  plans: PlanOption[];
  isAdmin: boolean;
  onUpdated: () => void;
}

export const GeneralTab = ({ tenant, plans, isAdmin, onUpdated }: GeneralTabProps) => {
  const [name, setName] = useState(tenant.name);
  const [connection, setConnection] = useState(tenant.connection);
  const [plan, setPlan] = useState(tenant.plan ?? 'free');
  const [error, setError] = useState<string | null>(null);

  const saveDetails = useMutation({
    mutationFn: () => controlPlaneClient.updateTenant(tenant.id, { name, connection }),
    onSuccess: () => {
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const savePlan = useMutation({
    mutationFn: () => controlPlaneClient.changePlan(tenant.id, plan),
    onSuccess: () => {
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const toggleSuspend = useMutation({
    mutationFn: () =>
      tenant.status === 'suspended'
        ? controlPlaneClient.unsuspendTenant(tenant.id)
        : controlPlaneClient.suspendTenant(tenant.id),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div className="cp-grid cp-grid--2">
      {error && <div className="cp-alert cp-alert--danger cp-span-full">{error}</div>}

      <section className="cp-card">
        <h3>Tenant details</h3>
        {isAdmin ? (
          <form
            className="cp-form"
            onSubmit={(e) => {
              e.preventDefault();
              saveDetails.mutate();
            }}
          >
            <label className="cp-field">
              <span>Name</span>
              <input value={name} onChange={(e) => setName(e.target.value)} required />
            </label>
            <label className="cp-field">
              <span>Connection</span>
              <input value={connection} onChange={(e) => setConnection(e.target.value)} required />
            </label>
            <button type="submit" className="cp-btn cp-btn--primary" disabled={saveDetails.isPending}>
              Save changes
            </button>
          </form>
        ) : (
          <dl className="cp-dl">
            <dt>Name</dt>
            <dd>{tenant.name}</dd>
            <dt>Connection</dt>
            <dd>
              <code>{tenant.connection}</code>
            </dd>
          </dl>
        )}
      </section>

      <section className="cp-card">
        <h3>Plan & status</h3>
        {isAdmin ? (
          <form
            className="cp-form cp-form--inline"
            onSubmit={(e) => {
              e.preventDefault();
              savePlan.mutate();
            }}
          >
            <select value={plan} onChange={(e) => setPlan(e.target.value)}>
              {plans.map((p) => (
                <option key={p.id} value={p.name}>
                  {p.name}
                  {p.is_default ? ' (default)' : ''}
                </option>
              ))}
            </select>
            <button type="submit" className="cp-btn cp-btn--ghost" disabled={savePlan.isPending}>
              Change plan
            </button>
          </form>
        ) : (
          <p className="cp-muted">Plan: {tenant.plan ?? 'free'}</p>
        )}

        <div className="cp-status-row">
          <TenantStatusBadge status={tenant.status} />
          {isAdmin && (
            <button
              type="button"
              className="cp-btn cp-btn--ghost"
              disabled={toggleSuspend.isPending}
              onClick={() => {
                if (
                  tenant.status !== 'suspended' &&
                  !window.confirm(`¿Suspender tenant ${tenant.name}?`)
                ) {
                  return;
                }
                toggleSuspend.mutate();
              }}
            >
              {tenant.status === 'suspended' ? 'Reactivar' : 'Suspender'}
            </button>
          )}
        </div>
      </section>
    </div>
  );
};