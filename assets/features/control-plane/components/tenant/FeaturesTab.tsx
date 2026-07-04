import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import {
  Badge,
  Button,
  Card,
  EmptyState,
  FormField,
  SelectField,
  TextAreaField,
  TextField,
  Toggle,
} from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { FeatureOverrideRecord } from '../../types';
import { RowActions, useConfirm } from '@nubitio/ui';
import { CpAlert, CpFormActions } from '../layout';

interface FeaturesTabProps {
  tenantId: number;
  isAdmin: boolean;
}

const S = CP_STRINGS.tenantTabs.features;

const emptyForm = () => ({
  featureKey: '',
  isEnabled: true,
  configJson: '',
  expiresAt: '',
  reason: '',
});

const statusBadge = (override: FeatureOverrideRecord) => {
  if (override.cleanedAt) {
    return (
      <Badge variant="secondary" pill>
        {S.statusCleaned}
      </Badge>
    );
  }
  if (override.isExpired) {
    return (
      <Badge variant="warning" pill>
        {S.statusExpired}
      </Badge>
    );
  }
  return (
    <Badge variant="success" pill>
      {S.statusActive}
    </Badge>
  );
};

export const FeaturesTab = ({ tenantId, isAdmin }: FeaturesTabProps) => {
  const { ask, ConfirmHost } = useConfirm();
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
    return <p className="cp-muted">{CP_STRINGS.loading}</p>;
  }

  if (features.error || !features.data) {
    return (
      <CpAlert tone="danger">
        {(features.error as Error)?.message ?? S.loadError}
      </CpAlert>
    );
  }

  return (
    <div className="cp-stack">
      <CpAlert tone="warning">{S.hint}</CpAlert>

      {error && <CpAlert tone="danger">{error}</CpAlert>}

      {isAdmin && (
        <CpFormActions>
          <Button
            onClick={() => {
              setShowForm(true);
              setEditingKey(null);
              setForm(emptyForm());
            }}
          >
            {S.addOverride}
          </Button>
        </CpFormActions>
      )}

      {(showForm || editingKey) && isAdmin && (
        <Card variant="panel" title={editingKey ? S.editOverride(editingKey) : S.newOverride}>
          <form
            className="cp-form"
            onSubmit={(e) => {
              e.preventDefault();
              if (editingKey) update.mutate(editingKey);
              else create.mutate();
            }}
          >
            {!editingKey && (
              <FormField label={S.featureKey}>
                <SelectField
                  value={form.featureKey}
                  required
                  onChange={(e) => setForm((f) => ({ ...f, featureKey: e.target.value }))}
                >
                  <option value="">{S.selectFeature}</option>
                  {features.data.featureKeys.map((key) => (
                    <option key={key} value={key}>
                      {key}
                    </option>
                  ))}
                </SelectField>
              </FormField>
            )}
            <Toggle
              label={S.colEnabled}
              checked={form.isEnabled}
              onChange={(checked) => setForm((f) => ({ ...f, isEnabled: checked }))}
            />
            <FormField label={S.configJson}>
              <TextAreaField
                className="cp-field__input--mono"
                rows={4}
                value={form.configJson}
                onChange={(e) => setForm((f) => ({ ...f, configJson: e.target.value }))}
              />
            </FormField>
            <FormField label={S.expiresAt}>
              <TextField
                type="datetime-local"
                value={form.expiresAt}
                onChange={(e) => setForm((f) => ({ ...f, expiresAt: e.target.value }))}
              />
            </FormField>
            <FormField label={S.reason}>
              <TextField
                value={form.reason}
                onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
              />
            </FormField>
            <CpFormActions>
              <Button type="submit" loading={editingKey ? update.isPending : create.isPending}>
                {editingKey ? CP_STRINGS.save : CP_STRINGS.create}
              </Button>
              <Button
                type="button"
                variant="ghost"
                onClick={() => {
                  setShowForm(false);
                  setEditingKey(null);
                  setForm(emptyForm());
                }}
              >
                {CP_STRINGS.cancel}
              </Button>
            </CpFormActions>
          </form>
        </Card>
      )}

      <Card variant="panel">
        {features.data.overrides.length === 0 ? (
          <EmptyState icon="toggle-right" title={S.empty} size="sm" />
        ) : (
          <div className="cp-data-table-wrap">
            <table className="cp-data-table">
              <thead>
                <tr>
                  <th>{S.colFeature}</th>
                  <th>{S.colEnabled}</th>
                  <th>{S.colExpires}</th>
                  <th>{S.colStatus}</th>
                  <th>{S.colReason}</th>
                  {isAdmin && <th />}
                </tr>
              </thead>
              <tbody>
                {features.data.overrides.map((override) => (
                  <tr key={override.id}>
                    <td>
                      <code className="cp-code">{override.featureKey}</code>
                    </td>
                    <td>{override.isEnabled ? CP_STRINGS.yes : CP_STRINGS.no}</td>
                    <td className="cp-muted">{override.expiresAt ?? '—'}</td>
                    <td>{statusBadge(override)}</td>
                    <td className="cp-muted">{override.reason ?? '—'}</td>
                    {isAdmin && (
                      <td className="cp-data-table__actions">
                        <RowActions
                          items={[
                            {
                              label: CP_STRINGS.edit,
                              icon: 'pencil-simple',
                              onClick: () => startEdit(override),
                            },
                            {
                              separator: true,
                              label: CP_STRINGS.delete,
                              icon: 'trash',
                              danger: true,
                              onClick: () =>
                                ask({
                                  title: S.deleteConfirmTitle,
                                  message: S.deleteConfirm(override.featureKey),
                                  variant: 'danger',
                                  onConfirm: () => remove.mutate(override.featureKey),
                                }),
                            },
                          ]}
                        />
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      <ConfirmHost />
    </div>
  );
};