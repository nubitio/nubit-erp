import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import type { FeatureOverrideRecord } from '../../types';

interface FeaturesTabProps {
  tenantId: number;
  isAdmin: boolean;
}

const emptyForm = () => ({
  featureKey: '',
  isEnabled: true,
  configJson: '',
  expiresAt: '',
  reason: '',
});

export const FeaturesTab = ({ tenantId, isAdmin }: FeaturesTabProps) => {
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingKey, setEditingKey] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm());

  const features = useQuery({
    queryKey: ['control-plane', 'tenant', tenantId, 'features'],
    queryFn: () => controlPlaneClient.getTenantFeatures(tenantId),
  });

  const refresh = () => features.refetch();

  const create = useMutation({
    mutationFn: () => controlPlaneClient.createTenantFeature(tenantId, form),
    onSuccess: () => {
      setShowForm(false);
      setForm(emptyForm());
      setError(null);
      refresh();
    },
    onError: (err: Error) => setError(err.message),
  });

  const update = useMutation({
    mutationFn: (key: string) =>
      controlPlaneClient.updateTenantFeature(tenantId, key, {
        isEnabled: form.isEnabled,
        configJson: form.configJson || undefined,
        expiresAt: form.expiresAt || undefined,
        reason: form.reason || undefined,
      }),
    onSuccess: () => {
      setEditingKey(null);
      setForm(emptyForm());
      setError(null);
      refresh();
    },
    onError: (err: Error) => setError(err.message),
  });

  const remove = useMutation({
    mutationFn: (key: string) => controlPlaneClient.deleteTenantFeature(tenantId, key),
    onSuccess: refresh,
    onError: (err: Error) => setError(err.message),
  });

  const startEdit = (override: FeatureOverrideRecord) => {
    setEditingKey(override.featureKey);
    setShowForm(false);
    setForm({
      featureKey: override.featureKey,
      isEnabled: override.isEnabled,
      configJson: override.configJson ? JSON.stringify(override.configJson, null, 2) : '',
      expiresAt: override.expiresAt?.slice(0, 16) ?? '',
      reason: override.reason ?? '',
    });
  };

  if (features.isLoading) {
    return <div className="cp-page--loading">Loading feature overrides…</div>;
  }

  if (features.error || !features.data) {
    return (
      <div className="cp-alert cp-alert--danger">
        {(features.error as Error)?.message ?? 'Failed to load features'}
      </div>
    );
  }

  const statusBadge = (override: FeatureOverrideRecord) => {
    if (override.cleanedAt) return <span className="cp-badge">Cleaned</span>;
    if (override.isExpired) return <span className="cp-badge cp-badge--suspended">Expired</span>;
    return <span className="cp-badge cp-badge--active">Active</span>;
  };

  return (
    <div className="cp-stack">
      <div className="cp-alert cp-alert--warning">
        Active override → plan feature → disabled (fail-closed). Expired overrides are ignored.
      </div>

      {error && <div className="cp-alert cp-alert--danger">{error}</div>}

      {isAdmin && (
        <div className="cp-form__actions">
          <button
            type="button"
            className="cp-btn cp-btn--primary"
            onClick={() => {
              setShowForm(true);
              setEditingKey(null);
              setForm(emptyForm());
            }}
          >
            Add override
          </button>
        </div>
      )}

      {(showForm || editingKey) && isAdmin && (
        <section className="cp-card">
          <h3>{editingKey ? `Edit ${editingKey}` : 'New override'}</h3>
          <form
            className="cp-form"
            onSubmit={(e) => {
              e.preventDefault();
              if (editingKey) update.mutate(editingKey);
              else create.mutate();
            }}
          >
            {!editingKey && (
              <label className="cp-field">
                <span>Feature key</span>
                <select
                  value={form.featureKey}
                  required
                  onChange={(e) => setForm((f) => ({ ...f, featureKey: e.target.value }))}
                >
                  <option value="">Select feature…</option>
                  {features.data.featureKeys.map((key) => (
                    <option key={key} value={key}>
                      {key}
                    </option>
                  ))}
                </select>
              </label>
            )}
            <label className="cp-check">
              <input
                type="checkbox"
                checked={form.isEnabled}
                onChange={(e) => setForm((f) => ({ ...f, isEnabled: e.target.checked }))}
              />
              Enabled
            </label>
            <label className="cp-field">
              <span>Config JSON</span>
              <textarea
                className="cp-field__input cp-field__input--mono"
                rows={4}
                value={form.configJson}
                onChange={(e) => setForm((f) => ({ ...f, configJson: e.target.value }))}
              />
            </label>
            <label className="cp-field">
              <span>Expires at</span>
              <input
                type="datetime-local"
                className="cp-field__input"
                value={form.expiresAt}
                onChange={(e) => setForm((f) => ({ ...f, expiresAt: e.target.value }))}
              />
            </label>
            <label className="cp-field">
              <span>Reason</span>
              <input
                value={form.reason}
                onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
              />
            </label>
            <div className="cp-form__actions">
              <button type="submit" className="cp-btn cp-btn--primary">
                {editingKey ? 'Save' : 'Create'}
              </button>
              <button
                type="button"
                className="cp-btn cp-btn--ghost"
                onClick={() => {
                  setShowForm(false);
                  setEditingKey(null);
                  setForm(emptyForm());
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        </section>
      )}

      <section className="cp-card">
        {features.data.overrides.length === 0 ? (
          <div className="cp-empty">No feature overrides configured.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>Feature</th>
                <th>Enabled</th>
                <th>Expires</th>
                <th>Status</th>
                <th>Reason</th>
                {isAdmin && <th />}
              </tr>
            </thead>
            <tbody>
              {features.data.overrides.map((override) => (
                <tr key={override.id}>
                  <td>
                    <code>{override.featureKey}</code>
                  </td>
                  <td>{override.isEnabled ? 'Yes' : 'No'}</td>
                  <td className="cp-muted">{override.expiresAt ?? '—'}</td>
                  <td>{statusBadge(override)}</td>
                  <td className="cp-muted">{override.reason ?? '—'}</td>
                  {isAdmin && (
                    <td className="cp-table__actions">
                      <button
                        type="button"
                        className="cp-btn cp-btn--ghost"
                        onClick={() => startEdit(override)}
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        className="cp-btn cp-btn--ghost"
                        onClick={() => {
                          if (window.confirm(`Delete override for ${override.featureKey}?`)) {
                            remove.mutate(override.featureKey);
                          }
                        }}
                      >
                        Delete
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};