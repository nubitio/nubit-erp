import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CP_STRINGS } from '../i18n/es';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

export const PlanFeaturesPage = () => {
  const queryClient = useQueryClient();
  const { isAdmin } = useControlPlaneRoles();
  const [error, setError] = useState<string | null>(null);
  const [savedCell, setSavedCell] = useState<string | null>(null);

  const { data, isLoading, error: loadError } = useQuery({
    queryKey: ['control-plane', 'plan-features'],
    queryFn: () => controlPlaneClient.getPlanFeatures(),
  });

  const update = useMutation({
    mutationFn: ({
      planName,
      featureKey,
      isEnabled,
      configJson,
    }: {
      planName: string;
      featureKey: string;
      isEnabled: boolean;
      configJson: string;
    }) =>
      controlPlaneClient.updatePlanFeature(planName, featureKey, {
        isEnabled,
        configJson: configJson.trim() === '' ? null : configJson,
      }),
    onSuccess: (_, vars) => {
      setError(null);
      setSavedCell(`${vars.planName}:${vars.featureKey}`);
      queryClient.invalidateQueries({ queryKey: ['control-plane', 'plan-features'] });
      setTimeout(() => setSavedCell(null), 2000);
    },
    onError: (err: Error) => setError(err.message),
  });

  if (!isAdmin) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">Admin access required.</div>
      </div>
    );
  }

  if (isLoading) {
    return <div className="cp-page cp-page--loading">Cargando feature matrix…</div>;
  }

  if (loadError || !data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          {(loadError as Error)?.message ?? 'No se pudieron cargar las funciones del plan'}
        </div>
      </div>
    );
  }

  if (data.plans.length === 0) {
    return (
      <div className="cp-page">
        <header className="cp-page__header">
          <h1>Plan Feature Matrix</h1>
        </header>
        <div className="cp-empty">
          No plans found. <Link to="/plans">Create a plan first</Link>.
        </div>
      </div>
    );
  }

  return (
    <div className="cp-page">
      <header className="cp-page__header cp-page__header--split">
        <div>
          <Link to="/plans" className="cp-breadcrumb">
            ← Plans
          </Link>
          <h1>Plan Feature Matrix</h1>
          <p>Toggle features per plan. Active tenant overrides take priority.</p>
        </div>
      </header>

      {error && <div className="cp-alert cp-alert--danger">{error}</div>}

      <div className="cp-matrix-wrap">
        <table className="cp-table cp-table--matrix">
          <thead>
            <tr>
              <th>Feature</th>
              {data.plans.map((plan) => (
                <th key={plan.id}>
                  {plan.name}
                  {plan.is_default && (
                    <span className="cp-badge cp-badge--info">Default</span>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.featureKeys.map((featureKey) => {
              const isConfig = data.configFeatureKeys.includes(featureKey);
              return (
                <tr key={featureKey}>
                  <td>
                    <code>{featureKey}</code>
                    {isConfig && <span className="cp-badge">config</span>}
                  </td>
                  {data.plans.map((plan) => {
                    const cell = data.matrix[featureKey]?.[plan.name] ?? {
                      isEnabled: false,
                      configJson: null,
                    };
                    const cellId = `${plan.name}:${featureKey}`;
                    return (
                      <PlanFeatureCell
                        key={cellId}
                        featureKey={featureKey}
                        planName={plan.name}
                        isEnabled={cell.isEnabled}
                        configJson={
                          cell.configJson ? JSON.stringify(cell.configJson, null, 2) : ''
                        }
                        isConfig={isConfig}
                        saving={update.isPending}
                        saved={savedCell === cellId}
                        onSave={(payload) => update.mutate({ planName: plan.name, featureKey, ...payload })}
                      />
                    );
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};

const PlanFeatureCell = ({
  isEnabled,
  configJson,
  isConfig,
  saving,
  saved,
  onSave,
}: {
  featureKey: string;
  planName: string;
  isEnabled: boolean;
  configJson: string;
  isConfig: boolean;
  saving: boolean;
  saved: boolean;
  onSave: (payload: { isEnabled: boolean; configJson: string }) => void;
}) => {
  const [enabled, setEnabled] = useState(isEnabled);
  const [config, setConfig] = useState(configJson);

  useEffect(() => {
    setEnabled(isEnabled);
    setConfig(configJson);
  }, [isEnabled, configJson]);

  return (
    <td className={`cp-matrix-cell${enabled ? ' cp-matrix-cell--on' : ''}`}>
      <label className="cp-check">
        <input type="checkbox" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
        Enabled
      </label>
      {isConfig && (
        <textarea
          className="cp-field__input cp-field__input--mono"
          rows={3}
          value={config}
          onChange={(e) => setConfig(e.target.value)}
          placeholder="{}"
        />
      )}
      <button
        type="button"
        className="cp-btn cp-btn--ghost"
        disabled={saving}
        onClick={() => onSave({ isEnabled: enabled, configJson: config })}
      >
        {saved ? CP_STRINGS.saved : CP_STRINGS.save}
      </button>
    </td>
  );
};