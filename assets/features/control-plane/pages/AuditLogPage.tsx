import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CP_STRINGS } from '../i18n/es';

const truncate = (value: string, max = 120) =>
  value.length > max ? `${value.slice(0, max)}…` : value;

export const AuditLogPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [metadataPreview, setMetadataPreview] = useState<string | null>(null);

  const action = searchParams.get('action') ?? '';
  const dateFrom = searchParams.get('date_from') ?? '';
  const dateTo = searchParams.get('date_to') ?? '';
  const page = Math.max(1, Number(searchParams.get('page') ?? '1'));

  const queryParams = new URLSearchParams();
  if (action) queryParams.set('action', action);
  if (dateFrom) queryParams.set('date_from', dateFrom);
  if (dateTo) queryParams.set('date_to', dateTo);
  queryParams.set('page', String(page));

  const { data, isLoading, error } = useQuery({
    queryKey: ['control-plane', 'audit-log', action, dateFrom, dateTo, page],
    queryFn: () => controlPlaneClient.getAuditLog(queryParams),
  });

  const applyFilters = (form: HTMLFormElement) => {
    const formData = new FormData(form);
    const next = new URLSearchParams();
    const nextAction = String(formData.get('action') ?? '');
    const nextFrom = String(formData.get('date_from') ?? '');
    const nextTo = String(formData.get('date_to') ?? '');
    if (nextAction) next.set('action', nextAction);
    if (nextFrom) next.set('date_from', nextFrom);
    if (nextTo) next.set('date_to', nextTo);
    setSearchParams(next);
  };

  const goToPage = (nextPage: number) => {
    const next = new URLSearchParams(searchParams);
    if (nextPage <= 1) next.delete('page');
    else next.set('page', String(nextPage));
    setSearchParams(next);
  };

  if (isLoading) {
    return <div className="cp-page cp-page--loading">Cargando audit log…</div>;
  }

  if (error || !data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          No se pudo cargar audit log: {(error as Error)?.message ?? 'Error desconocido'}
        </div>
      </div>
    );
  }

  return (
    <div className="cp-page">
      <header className="cp-page__header">
        <div>
          <h1>Audit Log</h1>
          <p>{data.total.toLocaleString()} total entries</p>
        </div>
      </header>

      <section className="cp-card cp-section">
        <form
          className="cp-form cp-form--grid"
          onSubmit={(e) => {
            e.preventDefault();
            applyFilters(e.currentTarget);
          }}
        >
          <label className="cp-field">
            <span>Action</span>
            <select name="action" defaultValue={action}>
              <option value="">All actions</option>
              {data.actions.map((act) => (
                <option key={act} value={act}>
                  {act}
                </option>
              ))}
            </select>
          </label>
          <label className="cp-field">
            <span>From</span>
            <input type="date" name="date_from" defaultValue={dateFrom} />
          </label>
          <label className="cp-field">
            <span>To</span>
            <input type="date" name="date_to" defaultValue={dateTo} />
          </label>
          <div className="cp-filter-actions">
            <button type="submit" className="cp-btn cp-btn--primary">
              Filter
            </button>
            <button
              type="button"
              className="cp-btn cp-btn--ghost"
              onClick={() => setSearchParams({})}
            >
              Clear
            </button>
          </div>
        </form>
      </section>

      <section className="cp-card">
        {data.logs.length === 0 ? (
          <div className="cp-empty">No audit log entries found.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Actor</th>
                <th>Channel</th>
                <th>Tenant</th>
                <th>Metadata</th>
              </tr>
            </thead>
            <tbody>
              {data.logs.map((log) => {
                const meta = log.metadata ?? '';
                return (
                  <tr key={log.id}>
                    <td className="cp-nowrap">{log.created_at}</td>
                    <td>
                      <code>{log.action}</code>
                    </td>
                    <td>{log.actor ?? '—'}</td>
                    <td>{log.channel ?? '—'}</td>
                    <td>{log.tenant_name ?? '—'}</td>
                    <td>
                      <span className="cp-muted">{truncate(meta)}</span>
                      {meta.length > 2 && (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          onClick={() => setMetadataPreview(meta)}
                        >
                          {CP_STRINGS.view}
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}

        {data.totalPages > 1 && (
          <nav className="cp-pagination" aria-label="Audit log pages">
            <button
              type="button"
              className="cp-btn cp-btn--ghost"
              disabled={page <= 1}
              onClick={() => goToPage(page - 1)}
            >
              ←
            </button>
            {Array.from({ length: Math.min(7, data.totalPages) }, (_, i) => {
              const start = Math.max(1, Math.min(page - 3, data.totalPages - 6));
              const p = start + i;
              if (p > data.totalPages) return null;
              return (
                <button
                  key={p}
                  type="button"
                  className={`cp-btn cp-btn--ghost${p === page ? ' cp-btn--active' : ''}`}
                  onClick={() => goToPage(p)}
                >
                  {p}
                </button>
              );
            })}
            <button
              type="button"
              className="cp-btn cp-btn--ghost"
              disabled={page >= data.totalPages}
              onClick={() => goToPage(page + 1)}
            >
              →
            </button>
          </nav>
        )}
      </section>

      {metadataPreview !== null && (
        <div className="cp-modal-backdrop" onClick={() => setMetadataPreview(null)}>
          <div className="cp-modal" onClick={(e) => e.stopPropagation()}>
            <header className="cp-modal__header">
              <h3>Metadata</h3>
              <button
                type="button"
                className="cp-btn cp-btn--ghost"
                onClick={() => setMetadataPreview(null)}
              >
                {CP_STRINGS.close}
              </button>
            </header>
            <pre className="cp-modal__body">
              {(() => {
                try {
                  return JSON.stringify(JSON.parse(metadataPreview), null, 2);
                } catch {
                  return metadataPreview;
                }
              })()}
            </pre>
          </div>
        </div>
      )}
    </div>
  );
};