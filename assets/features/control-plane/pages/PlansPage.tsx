import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  controlPlaneClient,
  type PlanLimits,
  type PlanRecord,
} from '../api/controlPlaneClient';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

const LIMIT_KEYS: Array<keyof PlanLimits> = [
  'users',
  'customers',
  'products',
  'invoices',
  'branches',
  'storage_mb',
];

const UNLIMITED_LIMITS: PlanLimits = {
  users: -1,
  customers: -1,
  products: -1,
  invoices: -1,
  branches: -1,
  storage_mb: -1,
};

const STARTER_LIMITS: PlanLimits = {
  users: 5,
  customers: 250,
  products: 120,
  invoices: 1500,
  branches: 2,
  storage_mb: 300,
};

const formatLimitLabel = (key: string) => key.replace(/_/g, ' ');

const formatLimitValue = (value: number) => (value === -1 ? '∞' : value.toLocaleString());

const emptyLimits = (): PlanLimits => ({ ...UNLIMITED_LIMITS });

interface PlanEditorProps {
  plan: PlanRecord;
  isAdmin: boolean;
  onSave: (payload: { name: string; limits: PlanLimits; isDefault: boolean }) => void;
  onDelete: () => void;
  saving: boolean;
  deleting: boolean;
}

const PlanEditor = ({ plan, isAdmin, onSave, onDelete, saving, deleting }: PlanEditorProps) => {
  const [name, setName] = useState(plan.name);
  const [limits, setLimits] = useState<PlanLimits>({ ...plan.limits });
  const [isDefault, setIsDefault] = useState(plan.is_default);

  const applyPreset = (preset: PlanLimits) => setLimits({ ...preset });

  if (!isAdmin) {
    return (
      <div className={`cp-plan-card${plan.is_default ? ' cp-plan-card--default' : ''}`}>
        <div className="cp-plan-card__header">
          <strong>{plan.name}</strong>
          {plan.is_default && <span className="cp-badge cp-badge--info">Default</span>}
        </div>
        <div className="cp-plan-card__limits">
          {LIMIT_KEYS.map((key) => (
            <div key={key} className="cp-plan-card__limit">
              <span className="cp-muted">{formatLimitLabel(key)}</span>
              <span>{formatLimitValue(plan.limits[key] ?? -1)}</span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <form
      className={`cp-plan-card${plan.is_default ? ' cp-plan-card--default' : ''}`}
      onSubmit={(e) => {
        e.preventDefault();
        onSave({ name, limits, isDefault });
      }}
    >
      <div className="cp-plan-card__header">
        <div className="cp-plan-card__title">
          <input
            className="cp-field__input"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
          />
          {plan.is_default && <span className="cp-badge cp-badge--info">Default</span>}
        </div>
        <div className="cp-plan-card__presets">
          <button
            type="button"
            className="cp-btn cp-btn--ghost"
            onClick={() => applyPreset(UNLIMITED_LIMITS)}
          >
            Unlimited
          </button>
          <button
            type="button"
            className="cp-btn cp-btn--ghost"
            onClick={() => applyPreset(STARTER_LIMITS)}
          >
            Starter
          </button>
        </div>
      </div>

      <div className="cp-form cp-form--grid">
        {LIMIT_KEYS.map((key) => (
          <label key={key} className="cp-field">
            <span>{formatLimitLabel(key)}</span>
            <input
              type="number"
              value={limits[key]}
              onChange={(e) =>
                setLimits((prev) => ({ ...prev, [key]: Number(e.target.value) }))
              }
            />
          </label>
        ))}
      </div>

      <div className="cp-plan-card__footer">
        <label className="cp-check">
          <input
            type="checkbox"
            checked={isDefault}
            onChange={(e) => setIsDefault(e.target.checked)}
          />
          Default plan
        </label>
        <div className="cp-plan-card__actions">
          <button type="submit" className="cp-btn cp-btn--primary" disabled={saving}>
            Save
          </button>
          <button
            type="button"
            className="cp-btn cp-btn--ghost"
            disabled={deleting}
            onClick={() => {
              if (window.confirm(`Delete plan ${plan.name}?`)) onDelete();
            }}
          >
            Delete
          </button>
        </div>
      </div>
    </form>
  );
};

export const PlansPage = () => {
  const queryClient = useQueryClient();
  const { isAdmin } = useControlPlaneRoles();
  const [createName, setCreateName] = useState('');
  const [createLimits, setCreateLimits] = useState<PlanLimits>(emptyLimits());
  const [createDefault, setCreateDefault] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading, error: loadError } = useQuery({
    queryKey: ['control-plane', 'plans'],
    queryFn: () => controlPlaneClient.getPlans(),
  });

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ['control-plane', 'plans'] });

  const create = useMutation({
    mutationFn: () =>
      controlPlaneClient.createPlan({
        name: createName,
        limits: createLimits,
        isDefault: createDefault,
      }),
    onSuccess: () => {
      setCreateName('');
      setCreateLimits(emptyLimits());
      setCreateDefault(false);
      setError(null);
      invalidate();
    },
    onError: (err: Error) => setError(err.message),
  });

  const update = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number;
      payload: { name: string; limits: PlanLimits; isDefault: boolean };
    }) => controlPlaneClient.updatePlan(id, payload),
    onSuccess: () => {
      setError(null);
      invalidate();
    },
    onError: (err: Error) => setError(err.message),
  });

  const remove = useMutation({
    mutationFn: (id: number) => controlPlaneClient.deletePlan(id),
    onSuccess: invalidate,
    onError: (err: Error) => setError(err.message),
  });

  if (isLoading) {
    return <div className="cp-page cp-page--loading">Cargando planes…</div>;
  }

  if (loadError || !data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          No se pudo cargar planes: {(loadError as Error)?.message ?? 'Error desconocido'}
        </div>
      </div>
    );
  }

  const defaultCount = data.plans.filter((p) => p.is_default).length;

  return (
    <div className="cp-page">
      <header className="cp-page__header cp-page__header--split">
        <div>
          <h1>Plans & limits</h1>
          <p>Define quota rules and defaults used by tenant provisioning.</p>
        </div>
        <Link to="/plan-features" className="cp-btn cp-btn--ghost">
          Manage features
        </Link>
      </header>

      <div className="cp-metrics cp-metrics--compact cp-section">
        <div className="cp-metric">
          <span className="cp-metric__label">Total plans</span>
          <span className="cp-metric__value cp-metric__value--sm">{data.plans.length}</span>
        </div>
        <div className="cp-metric">
          <span className="cp-metric__label">Default plans</span>
          <span className="cp-metric__value cp-metric__value--sm">{defaultCount}</span>
        </div>
        <div className="cp-metric">
          <span className="cp-metric__label">Quick rule</span>
          <span className="cp-metric__value cp-metric__value--sm">
            Use <code>-1</code> for unlimited
          </span>
        </div>
      </div>

      {error && <div className="cp-alert cp-alert--danger">{error}</div>}

      <div className="cp-grid cp-grid--2">
        {isAdmin && (
          <section className="cp-card">
            <h3>Create plan</h3>
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
                  value={createName}
                  onChange={(e) => setCreateName(e.target.value)}
                  placeholder="e.g. enterprise"
                  required
                />
              </label>
              <div className="cp-plan-card__presets">
                <button
                  type="button"
                  className="cp-btn cp-btn--ghost"
                  onClick={() => setCreateLimits({ ...UNLIMITED_LIMITS })}
                >
                  Set all unlimited
                </button>
                <button
                  type="button"
                  className="cp-btn cp-btn--ghost"
                  onClick={() => setCreateLimits({ ...STARTER_LIMITS })}
                >
                  Starter preset
                </button>
              </div>
              <div className="cp-form cp-form--grid">
                {LIMIT_KEYS.map((key) => (
                  <label key={key} className="cp-field">
                    <span>{formatLimitLabel(key)}</span>
                    <input
                      type="number"
                      value={createLimits[key]}
                      onChange={(e) =>
                        setCreateLimits((prev) => ({
                          ...prev,
                          [key]: Number(e.target.value),
                        }))
                      }
                    />
                  </label>
                ))}
              </div>
              <label className="cp-check">
                <input
                  type="checkbox"
                  checked={createDefault}
                  onChange={(e) => setCreateDefault(e.target.checked)}
                />
                Set as default
              </label>
              <button type="submit" className="cp-btn cp-btn--primary" disabled={create.isPending}>
                Create plan
              </button>
            </form>
          </section>
        )}

        <section className={`cp-stack${isAdmin ? '' : ' cp-span-full'}`}>
          <h2 className="cp-section__title">Existing plans</h2>
          {data.plans.length === 0 ? (
            <div className="cp-empty">No plans found.</div>
          ) : (
            data.plans.map((plan) => (
              <PlanEditor
                key={plan.id}
                plan={plan}
                isAdmin={isAdmin}
                saving={update.isPending}
                deleting={remove.isPending}
                onSave={(payload) => update.mutate({ id: plan.id, payload })}
                onDelete={() => remove.mutate(plan.id)}
              />
            ))
          )}
        </section>
      </div>
    </div>
  );
};