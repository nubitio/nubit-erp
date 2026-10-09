import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import {
  AppDropdown,
  Button,
  Card,
  DataTable,
  DateRangePicker,
  Drawer,
  FormField,
} from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import {
  CpAlert,
  CpForm,
  CpFormActions,
  CpPage,
  CpPageHeader,
  CpPageSkeleton,
} from '../components/layout';
import { CP_STRINGS } from '../i18n/es';

const truncate = (value: string, max = 120) =>
  value.length > max ? `${value.slice(0, max)}…` : value;

const formatMetadata = (metadata: string) => {
  try {
    return JSON.stringify(JSON.parse(metadata), null, 2);
  } catch {
    return metadata;
  }
};

export const AuditLogPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [metadataPreview, setMetadataPreview] = useState<string | null>(null);

  const action = searchParams.get('action') ?? '';
  const dateFrom = searchParams.get('date_from') ?? '';
  const dateTo = searchParams.get('date_to') ?? '';
  const page = Math.max(1, Number(searchParams.get('page') ?? '1'));

  const [draftAction, setDraftAction] = useState(action);
  const [draftFrom, setDraftFrom] = useState(dateFrom);
  const [draftTo, setDraftTo] = useState(dateTo);

  const queryParams = new URLSearchParams();
  if (action) queryParams.set('action', action);
  if (dateFrom) queryParams.set('date_from', dateFrom);
  if (dateTo) queryParams.set('date_to', dateTo);
  queryParams.set('page', String(page));

  const { data, isLoading, error } = useQuery({
    queryKey: ['control-plane', 'audit-log', action, dateFrom, dateTo, page],
    queryFn: () => controlPlaneClient.getAuditLog(queryParams),
  });

  const applyFilters = () => {
    const next = new URLSearchParams();
    if (draftAction) next.set('action', draftAction);
    if (draftFrom) next.set('date_from', draftFrom);
    if (draftTo) next.set('date_to', draftTo);
    setSearchParams(next);
  };

  const clearFilters = () => {
    setDraftAction('');
    setDraftFrom('');
    setDraftTo('');
    setSearchParams({});
  };

  const goToPage = (nextPage: number) => {
    const next = new URLSearchParams(searchParams);
    if (nextPage <= 1) next.delete('page');
    else next.set('page', String(nextPage));
    setSearchParams(next);
  };

  if (isLoading) {
    return <CpPageSkeleton variant="table" />;
  }

  if (error || !data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {CP_STRINGS.audit.loadError}: {(error as Error)?.message ?? CP_STRINGS.unknownError}
        </CpAlert>
      </CpPage>
    );
  }

  const actionOptions = [
    { value: '', label: CP_STRINGS.audit.allActions },
    ...data.actions.map((act) => ({ value: act, label: act })),
  ];

  return (
    <CpPage fill wide>
      <CpPageHeader
        title={CP_STRINGS.audit.title}
        subtitle={CP_STRINGS.audit.subtitle(data.total)}
      />

      <Card variant="panel">
        <CpForm
          variant="filter"
          onSubmit={(e) => {
            e.preventDefault();
            applyFilters();
          }}
        >
          <FormField label={CP_STRINGS.audit.action}>
            <AppDropdown
              value={draftAction}
              options={actionOptions}
              onChange={setDraftAction}
              variant="form"
              showFieldLabel={false}
              placeholder={CP_STRINGS.audit.allActions}
            />
          </FormField>
          <FormField label={CP_STRINGS.audit.dateRange} className="nb-span-full">
            <DateRangePicker
              startValue={draftFrom || null}
              endValue={draftTo || null}
              locale="es-PE"
              ariaLabel={CP_STRINGS.audit.dateRange}
              onChange={(start, end) => {
                setDraftFrom(start);
                setDraftTo(end);
              }}
            />
          </FormField>
          <CpFormActions>
            <Button type="submit">{CP_STRINGS.filter}</Button>
            <Button type="button" variant="ghost" onClick={clearFilters}>
              {CP_STRINGS.clear}
            </Button>
          </CpFormActions>
        </CpForm>
      </Card>

      <Card variant="panel" className="nb-table-surface">
        <DataTable
          fill
          variant="flush"
          rows={data.logs}
          getRowKey={(log) => log.id}
          emptyMessage={CP_STRINGS.audit.empty}
          columns={[
            {
              id: 'date',
              header: CP_STRINGS.audit.date,
              nowrap: true,
              accessor: (log) => log.created_at,
            },
            {
              id: 'action',
              header: CP_STRINGS.audit.action,
              render: (log) => <code>{log.action}</code>,
            },
            {
              id: 'actor',
              header: CP_STRINGS.audit.actor,
              accessor: (log) => log.actor ?? '—',
            },
            {
              id: 'channel',
              header: CP_STRINGS.audit.channel,
              accessor: (log) => log.channel ?? '—',
            },
            {
              id: 'tenant',
              header: CP_STRINGS.audit.tenant,
              accessor: (log) => log.tenant_name ?? '—',
            },
            {
              id: 'metadata',
              header: CP_STRINGS.audit.metadata,
              render: (log) => {
                const meta = log.metadata ?? '';
                return (
                  <>
                    <span className="nb-text-muted">{truncate(meta)}</span>
                    {meta.length > 2 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setMetadataPreview(meta)}
                      >
                        {CP_STRINGS.view}
                      </Button>
                    )}
                  </>
                );
              },
            },
          ]}
          pagination={{
            page,
            totalPages: data.totalPages,
            onPageChange: goToPage,
            ariaLabel: CP_STRINGS.audit.pages,
          }}
        />
      </Card>

      <Drawer
        isOpen={metadataPreview !== null}
        onClose={() => setMetadataPreview(null)}
        title={CP_STRINGS.audit.metadataTitle}
        width={720}
        scrim="overlay"
        closeLabel={CP_STRINGS.close}
      >
        <pre className="cp-drawer-pre">
          {metadataPreview ? formatMetadata(metadataPreview) : ''}
        </pre>
      </Drawer>
    </CpPage>
  );
};
