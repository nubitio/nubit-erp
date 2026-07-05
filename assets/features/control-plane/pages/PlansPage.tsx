import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Badge,
  Button,
  Card,
  Chip,
  Cluster,
  DescriptionList,
  Drawer,
  FormField,
  Row,
  Text,
  TextField,
  Toggle,
} from '@nubitio/ui';
import {
  controlPlaneClient,
  type PlanLimits,
  type PlanRecord,
} from '../api/controlPlaneClient';
import {
  CpAlert,
  CpForm,
  CpFormActions,
  CpMetricGrid,
  CpPage,
  CpPageHeader,
  CpPageSkeleton,
  CpSection,
  Grid,
  Stack,
} from '../components/layout';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { useConfirm } from '@nubitio/ui';
import { cpNotify } from '../utils/notify';
import { CP_STRINGS, formatLimitLabel, formatLimitValue } from '../i18n/es';

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

const emptyLimits = (): PlanLimits => ({ ...UNLIMITED_LIMITS });

const limitItems = (limits: PlanLimits) =>
  LIMIT_KEYS.map((key) => ({
    term: formatLimitLabel(key),
    value: formatLimitValue(limits[key] ?? -1),
  }));

const PlanPresets = ({ onSelect }: { onSelect: (preset: PlanLimits) => void }) => (
  <Cluster>
    <Chip label={CP_STRINGS.plans.presetUnlimited} onClick={() => onSelect({ ...UNLIMITED_LIMITS })} />
    <Chip label={CP_STRINGS.plans.presetStarter} onClick={() => onSelect({ ...STARTER_LIMITS })} />
  </Cluster>
);

interface PlanEditorProps {
  plan: PlanRecord;
  isAdmin: boolean;
  onSave: (payload: { name: string; limits: PlanLimits; isDefault: boolean }) => void;
  onDelete: () => void;
  saving: boolean;
  deleting: boolean;
}

const PlanEditor = ({ plan, isAdmin, onSave, onDelete, saving, deleting }: PlanEditorProps) => {
  const { ask, ConfirmHost } = useConfirm();
  const [name, setName] = useState(plan.name);
  const [limits, setLimits] = useState<PlanLimits>({ ...plan.limits });
  const [isDefault, setIsDefault] = useState(plan.is_default);

  const cardClass = plan.is_default ? 'cp-plan-card--default' : undefined;

  if (!isAdmin) {
    return (
      <Card variant="panel" className={cardClass} title={plan.name}>
        {plan.is_default && (
          <Badge variant="info" pill>
            {CP_STRINGS.default}
          </Badge>
        )}
        <DescriptionList items={limitItems(plan.limits)} />
      </Card>
    );
  }

  return (
    <Card variant="panel" className={cardClass}>
      <CpForm
        onSubmit={(e) => {
          e.preventDefault();
          onSave({ name, limits, isDefault });
        }}
      >
        <Row justify="between" align="start" wrap gap={3}>
          <FormField label={CP_STRINGS.plans.name}>
            <TextField value={name} onChange={(e) => setName(e.target.value)} required />
          </FormField>
          {plan.is_default && (
            <Badge variant="info" pill>
              {CP_STRINGS.default}
            </Badge>
          )}
          <PlanPresets onSelect={setLimits} />
        </Row>

        <Grid cols="auto" minColWidth="8rem">
          {LIMIT_KEYS.map((key) => (
            <FormField key={key} label={formatLimitLabel(key)}>
              <TextField
                type="number"
                value={limits[key]}
                onChange={(e) =>
                  setLimits((prev) => ({ ...prev, [key]: Number(e.target.value) }))
                }
              />
            </FormField>
          ))}
        </Grid>

        <Row justify="between" align="center" wrap gap={3}>
          <Toggle
            checked={isDefault}
            onChange={setIsDefault}
            label={CP_STRINGS.plans.setDefault}
          />
          <CpFormActions>
            <Button type="submit" loading={saving}>
              {CP_STRINGS.save}
            </Button>
            <Button
              type="button"
              variant="ghost"
              loading={deleting}
              onClick={() =>
                ask({
                  title: CP_STRINGS.plans.deleteConfirmTitle,
                  message: CP_STRINGS.plans.deleteConfirm(plan.name),
                  variant: 'danger',
                  onConfirm: onDelete,
                })
              }
            >
              {CP_STRINGS.delete}
            </Button>
          </CpFormActions>
        </Row>
      </CpForm>
      <ConfirmHost />
    </Card>
  );
};

export const PlansPage = () => {
  const queryClient = useQueryClient();
  const { isAdmin } = useControlPlaneRoles();
  const [createOpen, setCreateOpen] = useState(false);
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

  const closeCreate = () => {
    setCreateOpen(false);
    setError(null);
  };

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
      setCreateOpen(false);
      invalidate();
      cpNotify.success(CP_STRINGS.saved);
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
      cpNotify.success(CP_STRINGS.saved);
    },
    onError: (err: Error) => setError(err.message),
  });

  const remove = useMutation({
    mutationFn: (id: number) => controlPlaneClient.deletePlan(id),
    onSuccess: invalidate,
    onError: (err: Error) => setError(err.message),
  });

  if (isLoading) {
    return <CpPageSkeleton variant="table" />;
  }

  if (loadError || !data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {CP_STRINGS.plans.loadError}: {(loadError as Error)?.message ?? CP_STRINGS.unknownError}
        </CpAlert>
      </CpPage>
    );
  }

  const defaultCount = data.plans.filter((p) => p.is_default).length;

  return (
    <CpPage>
      <CpPageHeader
        title={CP_STRINGS.plans.title}
        subtitle={CP_STRINGS.plans.subtitle}
        actions={
          <Cluster>
            {isAdmin && (
              <Button onClick={() => setCreateOpen(true)}>{CP_STRINGS.plans.createTitle}</Button>
            )}
            <Link to="/plan-features">
              <Button variant="ghost">{CP_STRINGS.plans.manageFeatures}</Button>
            </Link>
          </Cluster>
        }
      />

      <CpMetricGrid
        layout="strip"
        density="compact"
        items={[
          { label: CP_STRINGS.plans.totalPlans, value: data.plans.length, icon: 'package' },
          { label: CP_STRINGS.plans.defaultPlans, value: defaultCount, icon: 'star' },
          { label: CP_STRINGS.plans.quickRule, value: '-1', icon: 'infinity' },
        ]}
      />

      {error && !createOpen && <CpAlert tone="danger">{error}</CpAlert>}

      <CpSection title={CP_STRINGS.plans.existingTitle}>
        {data.plans.length === 0 ? (
          <Text tone="muted">{CP_STRINGS.plans.empty}</Text>
        ) : (
          <Stack>
            {data.plans.map((plan) => (
              <PlanEditor
                key={plan.id}
                plan={plan}
                isAdmin={isAdmin}
                saving={update.isPending}
                deleting={remove.isPending}
                onSave={(payload) => update.mutate({ id: plan.id, payload })}
                onDelete={() => remove.mutate(plan.id)}
              />
            ))}
          </Stack>
        )}
      </CpSection>

      <Drawer
        isOpen={createOpen}
        onClose={closeCreate}
        title={CP_STRINGS.plans.createTitle}
        width={480}
        scrim="overlay"
        closeLabel={CP_STRINGS.cancel}
        footer={
          <Button
            loading={create.isPending}
            fullWidth
            disabled={!createName.trim()}
            onClick={() => create.mutate()}
          >
            {CP_STRINGS.plans.createSubmit}
          </Button>
        }
      >
        {error && <CpAlert tone="danger">{error}</CpAlert>}
        <CpForm
          onSubmit={(e) => {
            e.preventDefault();
            create.mutate();
          }}
        >
          <FormField label={CP_STRINGS.plans.name}>
            <TextField
              value={createName}
              onChange={(e) => setCreateName(e.target.value)}
              placeholder={CP_STRINGS.plans.namePlaceholder}
              required
            />
          </FormField>
          <PlanPresets onSelect={setCreateLimits} />
          <Grid cols="auto" minColWidth="8rem">
            {LIMIT_KEYS.map((key) => (
              <FormField key={key} label={formatLimitLabel(key)}>
                <TextField
                  type="number"
                  value={createLimits[key]}
                  onChange={(e) =>
                    setCreateLimits((prev) => ({
                      ...prev,
                      [key]: Number(e.target.value),
                    }))
                  }
                />
              </FormField>
            ))}
          </Grid>
          <Toggle
            checked={createDefault}
            onChange={setCreateDefault}
            label={CP_STRINGS.plans.setDefault}
          />
        </CpForm>
      </Drawer>
    </CpPage>
  );
};
