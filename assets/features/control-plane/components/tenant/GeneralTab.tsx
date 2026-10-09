import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { AppDropdown, Button, Card, Code, FormField, TextField } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { PlanOption, TenantRecord } from '../../types';
import { useConfirm } from '@nubitio/ui';
import { CpAlert, CpDescriptionList, CpForm, Col, Grid } from '../layout';
import { TenantStatusBadge } from '../TenantStatusBadge';

interface GeneralTabProps {
  tenant: TenantRecord;
  plans: PlanOption[];
  isAdmin: boolean;
  onUpdated: () => void;
}

const S = CP_STRINGS.tenantTabs.general;

export const GeneralTab = ({ tenant, plans, isAdmin, onUpdated }: GeneralTabProps) => {
  const { ask, ConfirmHost } = useConfirm();
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

  const planOptions = plans.map((p) => ({
    value: p.name,
    label: p.is_default ? `${p.name} (${CP_STRINGS.default})` : p.name,
  }));

  return (
    <Grid cols={2} gap={4}>
      {error && (
        <Col fullWidth>
          <CpAlert tone="danger">{error}</CpAlert>
        </Col>
      )}

      <Card variant="panel" title={S.detailsTitle}>
        {isAdmin ? (
          <CpForm
            onSubmit={(e) => {
              e.preventDefault();
              saveDetails.mutate();
            }}
          >
            <FormField label={S.name}>
              <TextField value={name} onChange={(e) => setName(e.target.value)} required />
            </FormField>
            <FormField label={S.connection}>
              <TextField
                value={connection}
                onChange={(e) => setConnection(e.target.value)}
                required
              />
            </FormField>
            <Button type="submit" loading={saveDetails.isPending} fullWidth>
              {S.saveDetails}
            </Button>
          </CpForm>
        ) : (
          <CpDescriptionList
            items={[
              { term: S.name, value: tenant.name },
              {
                term: S.connection,
                value: <Code>{tenant.connection}</Code>,
              },
            ]}
          />
        )}
      </Card>

      <Card variant="panel" title={S.planTitle}>
        {isAdmin ? (
          <CpForm
            variant="inline"
            onSubmit={(e) => {
              e.preventDefault();
              savePlan.mutate();
            }}
          >
            <FormField label={S.plan}>
              <AppDropdown
                value={plan}
                options={planOptions}
                onChange={setPlan}
                variant="form"
                showFieldLabel={false}
              />
            </FormField>
            <Button type="submit" variant="ghost" loading={savePlan.isPending}>
              {S.changePlan}
            </Button>
          </CpForm>
        ) : (
          <p className="cp-muted">
            {S.plan}: {tenant.plan ?? 'free'}
          </p>
        )}

        <div className="cp-status-row">
          <TenantStatusBadge status={tenant.status} />
          {isAdmin && (
            <Button
              type="button"
              variant="ghost"
              loading={toggleSuspend.isPending}
              onClick={() => {
                if (tenant.status === 'suspended') {
                  toggleSuspend.mutate();
                  return;
                }
                ask({
                  title: CP_STRINGS.tenantDetail.suspendConfirmTitle,
                  message: CP_STRINGS.tenantDetail.suspendConfirm(tenant.name),
                  variant: 'warning',
                  onConfirm: () => toggleSuspend.mutate(),
                });
              }}
            >
              {tenant.status === 'suspended'
                ? CP_STRINGS.tenants.unsuspend
                : CP_STRINGS.tenants.suspend}
            </Button>
          )}
        </div>
      </Card>
      <ConfirmHost />
    </Grid>
  );
};
