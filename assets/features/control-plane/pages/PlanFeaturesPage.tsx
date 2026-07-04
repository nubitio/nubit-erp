import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Badge, Button, Card, TextAreaField, Toggle } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CpAlert, CpPage, CpPageHeader, CpPageSkeleton } from '../components/layout';
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
      <CpPage>
        <CpAlert tone="danger">{CP_STRINGS.adminRequired}</CpAlert>
      </CpPage>
    );
  }

  if (isLoading) {
    return <CpPageSkeleton variant="matrix" />;
  }

  if (loadError || !data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {(loadError as Error)?.message ?? CP_STRINGS.planFeatures.loadError}
        </CpAlert>
      </CpPage>
    );
  }

  if (data.plans.length === 0) {
    return (
      <CpPage>
        <CpPageHeader title={CP_STRINGS.planFeatures.title} />
        <Card variant="panel">
          <p className="cp-muted">
            {CP_STRINGS.planFeatures.empty}
            <Link to="/plans">{CP_STRINGS.planFeatures.createPlanFirst}</Link>.
          </p>
        </Card>
      </CpPage>
    );
  }

  return (
    <CpPage>
      <CpPageHeader
        title={CP_STRINGS.planFeatures.title}
        subtitle={CP_STRINGS.planFeatures.subtitle}
        breadcrumb={{ label: CP_STRINGS.plans.title, to: '/plans' }}
      />

      {error && <CpAlert tone="danger">{error}</CpAlert>}

      <Card variant="panel">
        <div className="cp-matrix-wrap">
          <table className="cp-table--matrix">
            <thead>
              <tr>
                <th>{CP_STRINGS.planFeatures.feature}</th>
                {data.plans.map((plan) => (
                  <th key={plan.id}>
                    {plan.name}
                    {plan.is_default && (
                      <Badge variant="info" size="sm" pill>
                        {CP_STRINGS.default}
                      </Badge>
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
                      {isConfig && (
                        <Badge variant="secondary" size="sm">
                          {CP_STRINGS.planFeatures.config}
                        </Badge>
                      )}
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
                          onSave={(payload) =>
                            update.mutate({ planName: plan.name, featureKey, ...payload })
                          }
                        />
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>
    </CpPage>
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
      <Toggle
        checked={enabled}
        onChange={setEnabled}
        label={CP_STRINGS.planFeatures.enabled}
        size="sm"
      />
      {isConfig && (
        <TextAreaField
          className="cp-field__input--mono"
          rows={3}
          value={config}
          onChange={(e) => setConfig(e.target.value)}
          placeholder="{}"
        />
      )}
      <Button
        type="button"
        variant="ghost"
        size="sm"
        loading={saving}
        onClick={() => onSave({ isEnabled: enabled, configJson: config })}
      >
        {saved ? CP_STRINGS.saved : CP_STRINGS.save}
      </Button>
    </td>
  );
};