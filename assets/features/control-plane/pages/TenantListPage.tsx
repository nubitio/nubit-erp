import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Card,
  Cluster,
  Code,
  DataTable,
  Drawer,
  FormField,
  RowActions,
  TextField,
  useConfirm,
} from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import {
  CpAlert,
  CpForm,
  CpPage,
  CpPageHeader,
  CpPageSkeleton,
  CpSection,
} from '../components/layout';
import { TenantStatusBadge } from '../components/TenantStatusBadge';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { CP_STRINGS } from '../i18n/es';
import { cpNotify } from '../utils/notify';

export const TenantListPage = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { isAdmin, isSuperAdmin } = useControlPlaneRoles();
  const { ask, ConfirmHost } = useConfirm();
  const [createOpen, setCreateOpen] = useState(false);
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

  const closeCreate = () => {
    setCreateOpen(false);
    setFormError(null);
  };

  const create = useMutation({
    mutationFn: () => controlPlaneClient.createTenant({ name, connection }),
    onSuccess: () => {
      setName('');
      setConnection('');
      setFormError(null);
      setCreateOpen(false);
      invalidate();
      cpNotify.success(CP_STRINGS.tenants.created);
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
    return <CpPageSkeleton variant="table" />;
  }

  if (error || !data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {CP_STRINGS.tenants.loadError}: {(error as Error)?.message ?? CP_STRINGS.unknownError}
        </CpAlert>
      </CpPage>
    );
  }

  return (
    <CpPage fill wide>
      <CpPageHeader
        title={CP_STRINGS.tenants.title}
        subtitle={CP_STRINGS.tenants.subtitle}
        badge={{ label: CP_STRINGS.tenants.activeCount(data.tenants.length) }}
        actions={
          isAdmin ? (
            <Button onClick={() => setCreateOpen(true)}>{CP_STRINGS.tenants.addTitle}</Button>
          ) : undefined
        }
      />

      {formError && !createOpen && <CpAlert tone="danger">{formError}</CpAlert>}

      <Card
        variant="panel"
        title={CP_STRINGS.tenants.listTitle(data.tenants.length)}
        className="nb-table-surface"
      >
        <DataTable
          fill
          variant="flush"
          rows={data.tenants}
          getRowKey={(tenant) => tenant.id}
          emptyMessage={CP_STRINGS.tenants.listEmpty}
          columns={[
            { id: 'id', header: CP_STRINGS.tenants.colId, accessor: (tenant) => tenant.id },
            {
              id: 'name',
              header: CP_STRINGS.tenants.colName,
              render: (tenant) => (
                <>
                  <strong>{tenant.name}</strong>
                  <div className="nb-text-muted">{tenant.connection}</div>
                </>
              ),
            },
            {
              id: 'plan',
              header: CP_STRINGS.tenants.colPlan,
              accessor: (tenant) => tenant.plan ?? '—',
            },
            {
              id: 'domain',
              header: CP_STRINGS.tenants.colDomain,
              accessor: (tenant) => tenant.primary_domain ?? '—',
            },
            {
              id: 'status',
              header: CP_STRINGS.tenants.colStatus,
              render: (tenant) => <TenantStatusBadge status={tenant.status} />,
            },
          ]}
          rowActions={(tenant) => [
            {
              label: CP_STRINGS.tenants.manage,
              icon: 'gear',
              onClick: () => navigate(`/tenants/${tenant.id}`),
            },
            tenant.status === 'suspended'
              ? {
                  label: CP_STRINGS.tenants.unsuspend,
                  icon: 'play',
                  onClick: () => unsuspend.mutate(tenant.id),
                }
              : {
                  label: CP_STRINGS.tenants.suspend,
                  icon: 'pause',
                  onClick: () =>
                    ask({
                      title: CP_STRINGS.tenants.suspendConfirmTitle,
                      message: CP_STRINGS.tenantDetail.suspendConfirm(tenant.name),
                      variant: 'warning',
                      onConfirm: () => suspend.mutate(tenant.id),
                    }),
                },
            ...(isSuperAdmin
              ? [
                  {
                    separator: true,
                    label: CP_STRINGS.delete,
                    icon: 'trash',
                    danger: true,
                    onClick: () =>
                      ask({
                        title: CP_STRINGS.tenants.deleteConfirmTitle,
                        message: CP_STRINGS.tenants.deleteConfirm(tenant.name),
                        variant: 'danger',
                        onConfirm: () => remove.mutate(tenant.id),
                      }),
                  },
                ]
              : []),
          ]}
        />
      </Card>

      {data.deletedTenants.length > 0 && (
        <CpSection title={CP_STRINGS.tenants.deletedTitle(data.deletedTenants.length)}>
          <DataTable
            variant="flush"
            rows={data.deletedTenants}
            getRowKey={(tenant) => tenant.id}
            columns={[
              { id: 'id', header: CP_STRINGS.tenants.colId, accessor: (tenant) => tenant.id },
              { id: 'name', header: CP_STRINGS.tenants.colName, accessor: (tenant) => tenant.name },
              {
                id: 'connection',
                header: CP_STRINGS.tenants.addConnection,
                render: (tenant) => <Code>{tenant.connection}</Code>,
              },
              {
                id: 'deletedAt',
                header: CP_STRINGS.tenants.colDeletedAt,
                muted: true,
                accessor: (tenant) => tenant.deleted_at ?? '—',
              },
            ]}
            renderActions={(tenant) =>
              purgeTarget?.id === tenant.id ? (
                <Cluster>
                  <TextField
                    value={purgeConfirm}
                    onChange={(e) => setPurgeConfirm(e.target.value)}
                    placeholder={CP_STRINGS.tenants.purgePlaceholder(tenant.name)}
                  />
                  <Button
                    size="sm"
                    loading={purge.isPending}
                    disabled={purgeConfirm !== tenant.name}
                    onClick={() => purge.mutate(tenant.id)}
                  >
                    {CP_STRINGS.tenants.purgeConfirm}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setPurgeTarget(null);
                      setPurgeConfirm('');
                    }}
                  >
                    {CP_STRINGS.cancel}
                  </Button>
                </Cluster>
              ) : (
                <RowActions
                  items={[
                    ...(isAdmin
                      ? [
                          {
                            label: CP_STRINGS.tenants.restore,
                            icon: 'arrow-counter-clockwise',
                            onClick: () => restore.mutate(tenant.id),
                          },
                        ]
                      : []),
                    ...(isSuperAdmin
                      ? [
                          {
                            label: CP_STRINGS.tenants.purge,
                            icon: 'fire',
                            danger: true,
                            onClick: () => {
                              setPurgeTarget({ id: tenant.id, name: tenant.name });
                              setPurgeConfirm('');
                            },
                          },
                        ]
                      : []),
                  ]}
                />
              )
            }
          />
        </CpSection>
      )}

      <Drawer
        isOpen={createOpen}
        onClose={closeCreate}
        title={CP_STRINGS.tenants.addTitle}
        width={420}
        scrim="overlay"
        closeLabel={CP_STRINGS.cancel}
        footer={
          <Button
            loading={create.isPending}
            fullWidth
            onClick={() => create.mutate()}
            disabled={!name.trim() || !connection.trim()}
          >
            {CP_STRINGS.tenants.addSubmit}
          </Button>
        }
      >
        {formError && <CpAlert tone="danger">{formError}</CpAlert>}
        <CpForm
          onSubmit={(e) => {
            e.preventDefault();
            create.mutate();
          }}
        >
          <FormField label={CP_STRINGS.tenants.addName} helpText={CP_STRINGS.tenants.addNameHint}>
            <TextField
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={CP_STRINGS.tenants.addNamePlaceholder}
              required
            />
          </FormField>
          <FormField
            label={CP_STRINGS.tenants.addConnection}
            helpText={CP_STRINGS.tenants.addConnectionHint}
          >
            <TextField
              value={connection}
              onChange={(e) => setConnection(e.target.value)}
              placeholder={CP_STRINGS.tenants.addConnectionPlaceholder}
              required
            />
          </FormField>
        </CpForm>
      </Drawer>

      <ConfirmHost />
    </CpPage>
  );
};
