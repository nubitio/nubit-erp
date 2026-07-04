import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { cpNotify } from '../utils/notify';
import { TenantStatusBadge } from '../components/TenantStatusBadge';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

export const TenantListPage = () => {
  const queryClient = useQueryClient();
  const { isAdmin, isSuperAdmin } = useControlPlaneRoles();
  const [name, setName] = useState('');
  const [connection, setConnection] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const [purgeTarget, setPurgeTarget] = useState<{ id: number; name: string } | null>(null);
  const [purgeConfirm, setPurgeConfirm] = useState('');

  const { data, isLoading, error } = useQuery({
    queryKey: ['control-plane', 'tenants'],
    queryFn: () => controlPlaneClient.getTenants(),
  });

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ['control-plane', 'tenants'] });

  const create = useMutation({
    mutationFn: () => controlPlaneClient.createTenant({ name, connection }),
    onSuccess: () => {
      setName('');
      setConnection('');
      setFormError(null);
      invalidate();
      cpNotify.success('Tenant creado.');
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const suspend = useMutation({
    mutationFn: (id: number) => controlPlaneClient.suspendTenant(id),
    onSuccess: invalidate,
  });

  const unsuspend = useMutation({
    mutationFn: (id: number) => controlPlaneClient.unsuspendTenant(id),
    onSuccess: invalidate,
  });

  const remove = useMutation({
    mutationFn: (id: number) => controlPlaneClient.deleteTenant(id),
    onSuccess: invalidate,
  });

  const restore = useMutation({
    mutationFn: (id: number) => controlPlaneClient.restoreTenant(id),
    onSuccess: invalidate,
  });

  const purge = useMutation({
    mutationFn: (id: number) => controlPlaneClient.purgeTenant(id),
    onSuccess: () => {
      setPurgeTarget(null);
      setPurgeConfirm('');
      invalidate();
    },
    onError: (err: Error) => setFormError(err.message),
  });

  if (isLoading) {
    return <div className="cp-page cp-page--loading">Cargando tenants…</div>;
  }

  if (error || !data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          No se pudo cargar tenants: {(error as Error)?.message ?? 'Error desconocido'}
        </div>
      </div>
    );
  }

  return (
    <div className="cp-page">
      <header className="cp-page__header cp-page__header--split">
        <div>
          <h1>Tenants</h1>
          <p>Registro de empresas y su estado operativo.</p>
        </div>
        <span className="cp-badge cp-badge--info">{data.tenants.length} activos</span>
      </header>

      <div className="cp-grid cp-grid--2">
        {formError && (
          <div className="cp-alert cp-alert--danger cp-span-full">{formError}</div>
        )}

        {isAdmin && (
          <section className="cp-card">
            <h3>Add tenant</h3>
            <form
              className="cp-form"
              onSubmit={(e) => {
                e.preventDefault();
                create.mutate();
              }}
            >
              <label className="cp-field">
                <span>Name</span>
                <input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="e.g. acme"
                  required
                />
                <span className="cp-muted">Tenant identifier used in commands and switching.</span>
              </label>
              <label className="cp-field">
                <span>Connection</span>
                <input
                  value={connection}
                  onChange={(e) => setConnection(e.target.value)}
                  placeholder="e.g. acme_db"
                  required
                />
                <span className="cp-muted">Database/schema name on your DB server.</span>
              </label>
              <button type="submit" className="cp-btn cp-btn--primary" disabled={create.isPending}>
                Save tenant
              </button>
            </form>
          </section>
        )}

        <section className={`cp-card${isAdmin ? '' : ' cp-span-full'}`}>
          <h3>Activos ({data.tenants.length})</h3>
          {data.tenants.length === 0 ? (
            <div className="cp-empty">No tenants yet. Create one to get started.</div>
          ) : (
            <table className="cp-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Plan</th>
                  <th>Dominio</th>
                  <th>Estado</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {data.tenants.map((tenant) => (
                  <tr key={tenant.id}>
                    <td>{tenant.id}</td>
                    <td>
                      <strong>{tenant.name}</strong>
                      <div className="cp-muted">{tenant.connection}</div>
                    </td>
                    <td>{tenant.plan ?? '—'}</td>
                    <td>{tenant.primary_domain ?? '—'}</td>
                    <td>
                      <TenantStatusBadge status={tenant.status} />
                    </td>
                    <td className="cp-table__actions">
                      {tenant.status === 'suspended' ? (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          disabled={unsuspend.isPending}
                          onClick={() => unsuspend.mutate(tenant.id)}
                        >
                          Reactivar
                        </button>
                      ) : (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          disabled={suspend.isPending}
                          onClick={() => suspend.mutate(tenant.id)}
                        >
                          Suspender
                        </button>
                      )}
                      <Link to={`/tenants/${tenant.id}`} className="cp-btn cp-btn--primary">
                        Gestionar
                      </Link>
                      {isSuperAdmin && (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          disabled={remove.isPending}
                          onClick={() => {
                            if (window.confirm(`Delete tenant ${tenant.name}?`)) {
                              remove.mutate(tenant.id);
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

      {data.deletedTenants.length > 0 && (
        <section className="cp-section">
          <h2>Eliminados ({data.deletedTenants.length})</h2>
          <table className="cp-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Connection</th>
                <th>Deleted at</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.deletedTenants.map((tenant) => (
                <tr key={tenant.id}>
                  <td>{tenant.id}</td>
                  <td>{tenant.name}</td>
                  <td>
                    <code>{tenant.connection}</code>
                  </td>
                  <td className="cp-muted">{tenant.deleted_at ?? '—'}</td>
                  <td className="cp-table__actions">
                    {isAdmin && (
                      <button
                        type="button"
                        className="cp-btn cp-btn--ghost"
                        disabled={restore.isPending}
                        onClick={() => restore.mutate(tenant.id)}
                      >
                        Restore
                      </button>
                    )}
                    {isSuperAdmin &&
                      (purgeTarget?.id === tenant.id ? (
                        <div className="cp-purge-confirm">
                          <input
                            className="cp-field__input"
                            value={purgeConfirm}
                            onChange={(e) => setPurgeConfirm(e.target.value)}
                            placeholder={`Type '${tenant.name}'`}
                          />
                          <button
                            type="button"
                            className="cp-btn cp-btn--primary"
                            disabled={
                              purgeConfirm !== tenant.name || purge.isPending
                            }
                            onClick={() => purge.mutate(tenant.id)}
                          >
                            Confirm purge
                          </button>
                          <button
                            type="button"
                            className="cp-btn cp-btn--ghost"
                            onClick={() => {
                              setPurgeTarget(null);
                              setPurgeConfirm('');
                            }}
                          >
                            Cancel
                          </button>
                        </div>
                      ) : (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          onClick={() => {
                            setPurgeTarget({ id: tenant.id, name: tenant.name });
                            setPurgeConfirm('');
                          }}
                        >
                          Purge
                        </button>
                      ))}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}
    </div>
  );
};