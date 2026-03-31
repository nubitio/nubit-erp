import React, { useCallback, useMemo, useState } from 'react';
import { Button } from 'devextreme-react';
import type { SelectionChangedEvent } from 'devextreme/ui/data_grid';
import { DxDataGrid } from '../datagrid/DxDataGrid';
import { DxDialog } from '../dialog/DxDialog';
import { DxForm, FORM_EVENTS } from '../form/DxForm';
import { useCrudPage } from './useCrudPage';
import { dispatch, useEvents } from '../event/EventHook';
import type { ResourceConfig } from './ResourceConfig';
import type { BulkAction } from './BulkAction';
import type { Field } from '../field/Field';
import { AuditTrailPanel } from './AuditTrailPanel';
import { useCoreTranslation } from '../i18n/useCoreTranslation';
import { DialogStoreProvider } from './DialogStoreProvider';
import { useCrudDialogStore } from './useCrudDialogStore';
import type { DialogMode } from './dialogStore';
import { ConfirmDialog } from '../../shared/components/ConfirmDialog';
import { resolveCrudResource } from './resolveCrudResource';

interface CrudPageProps<T extends Record<string, unknown> = Record<string, unknown>> {
  resource: ResourceConfig<T>;
  /**
   * Called whenever any form field value changes.
   * Receives a snapshot of the full current form data object.
   * Wire this to SmartCrudPage's formData state for reactive rule evaluation.
   */
  onFormDataChange?: (data: Record<string, unknown>) => void;
  /**
   * When provided, open the edit dialog for this record on mount.
   * Enables URL deep-linking (e.g. /products/42 → edit record 42).
   * Defaults to null — existing behaviour unchanged when omitted.
   */
  initialRecordId?: string | number | null;
  /**
   * When true, open the add dialog on mount.
   * Enables URL deep-linking (e.g. /products/new → open add dialog).
   * Defaults to false — existing behaviour unchanged when omitted.
   */
  initialIsNew?: boolean;
  computedValues?: Record<string, unknown>;
  initialFilters?: Record<string, string>;
  onFiltersChange?: (filters: Record<string, string>) => void;
}

function inferFilterOperator(field: Field): NonNullable<Field['selectedFilterOperation']> {
  if (field.selectedFilterOperation) {
    return field.selectedFilterOperation;
  }

  return field.valueType === 'string' ? 'contains' : '=';
}

function buildRoutingFilterRules(
  fields: Field[],
  initialFilters: Record<string, string>,
): Array<{ field: string; operator: string; value: string | number | boolean }> {
  return Object.entries(initialFilters).flatMap(([name, value]) => {
    const field = fields.find(
      (candidate) => candidate.name === name && candidate.filterable !== false,
    );
    if (!field || value === '') {
      return [];
    }

    return [
      {
        field: name,
        operator: inferFilterOperator(field),
        value,
      },
    ];
  });
}

/**
 * Public export — wraps CrudPageInner in its own DialogStoreProvider so that
 * existing pages do not need to add a provider themselves.
 */
export const CrudPage = <T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
  onFormDataChange,
  initialRecordId,
  initialIsNew,
  computedValues,
  initialFilters,
  onFiltersChange,
}: CrudPageProps<T>) => (
  <DialogStoreProvider>
    <CrudPageInner
      resource={resource}
      onFormDataChange={onFormDataChange}
      initialRecordId={initialRecordId}
      initialIsNew={initialIsNew}
      computedValues={computedValues}
      initialFilters={initialFilters}
      onFiltersChange={onFiltersChange}
    />
  </DialogStoreProvider>
);

const CrudPageInner = <T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
  onFormDataChange,
  initialRecordId = null,
  initialIsNew = false,
  computedValues = {},
  initialFilters = {},
  onFiltersChange,
}: CrudPageProps<T>) => {
  const { t } = useCoreTranslation();
  const resolvedInputResource = useMemo(() => resolveCrudResource(resource), [resource]);
  const {
    events,
    resource: resolvedResource,
    fields,
    formRef,
    permissions,
    selectionState,
    presetState,
  } = useCrudPage(resolvedInputResource);
  const datagridFields = useMemo(
    () => fields.filter((field) => field.isIdentity || field.visible !== false),
    [fields],
  );
  const routeAwareGridFields = useMemo(
    () =>
      datagridFields.map((field) => {
        const routeFilterValue = initialFilters[field.name];
        if (routeFilterValue === undefined) {
          if (field.filterValue === undefined) {
            return field;
          }

          return { ...field, filterValue: undefined };
        }

        return {
          ...field,
          filterValue: routeFilterValue,
          selectedFilterOperation: inferFilterOperator(field),
        };
      }),
    [datagridFields, initialFilters],
  );
  const routeAwareFilters = useMemo(
    () => [
      ...(resolvedResource.filter ?? []),
      ...buildRoutingFilterRules(routeAwareGridFields, initialFilters),
    ],
    [initialFilters, resolvedResource.filter, routeAwareGridFields],
  );

  const isInlineEditMode =
    resolvedResource.editMode === 'row' ||
    resolvedResource.editMode === 'cell' ||
    resolvedResource.editMode === 'batch';

  const hasMultipleSelection = (resolvedResource.bulkActions?.length ?? 0) > 0;
  const hasPresets = (resolvedResource.columnPresets?.length ?? 0) > 0;
  const hasAuditTrail = resolvedResource.auditTrail?.enabled === true;

  const [selectedRowId, setSelectedRowId] = useState<string | number | null>(null);
  const [auditOpen, setAuditOpen] = useState(false);
  const [confirmState, setConfirmState] = useState<{
    open: boolean;
    action: BulkAction | null;
  }>({ open: false, action: null });

  const {
    isOpen: dialogIsOpen,
    mode: dialogMode,
    openDialog,
    closeDialog,
  } = useCrudDialogStore(resolvedResource.id);

  const [on] = useEvents();

  // Subscribe to ADD / EDIT / SUCCESS events and mirror them into the store.
  const wireEventsRef = React.useRef(() => {
    if (events.ADD) {
      on<{ title?: string; rowData?: Record<string, unknown> } | undefined>(
        events.ADD,
        (payload) => {
          openDialog('add', payload?.rowData ?? null);
        },
      );
    }
    if (events.EDIT) {
      on<Record<string, unknown> | undefined>(events.EDIT, (rowData) => {
        openDialog('edit', rowData ?? null);
      });
    }
    if (events.SUCCESS) {
      on(events.SUCCESS, () => {
        closeDialog();
      });
    }
  });
  wireEventsRef.current = () => {
    if (events.ADD) {
      on<{ title?: string; rowData?: Record<string, unknown> } | undefined>(
        events.ADD,
        (payload) => {
          openDialog('add', payload?.rowData ?? null);
        },
      );
    }
    if (events.EDIT) {
      on<Record<string, unknown> | undefined>(events.EDIT, (rowData) => {
        openDialog('edit', rowData ?? null);
      });
    }
    if (events.SUCCESS) {
      on(events.SUCCESS, () => {
        closeDialog();
      });
    }
  };

  React.useEffect(() => {
    wireEventsRef.current();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Deep-link: open the appropriate dialog on mount when routing props are provided.
  React.useEffect(() => {
    if (initialRecordId != null) {
      openDialog('edit', { id: initialRecordId });
      return;
    }

    if (initialIsNew) {
      openDialog('add', null);
      return;
    }

    closeDialog();
  }, [closeDialog, initialIsNew, initialRecordId, openDialog]);

  // Subscribe to detail-grid row mutation events and call onDetailRowsChanged when defined.
  // The callback receives `formRef` so it can imperatively update header-form fields
  // (e.g. recalculate a `total` from line items).
  // Cleanup is handled automatically by useEvents' own unmount effect.
  const onDetailRowsChangedRef = React.useRef(resolvedResource.onDetailRowsChanged);
  onDetailRowsChangedRef.current = resolvedResource.onDetailRowsChanged;

  React.useEffect(() => {
    if (!onDetailRowsChangedRef.current) return;

    const handler = () => onDetailRowsChangedRef.current?.(formRef);
    on(FORM_EVENTS.ROW_ADDED, handler);
    on(FORM_EVENTS.ROW_UPDATED, handler);
    on(FORM_EVENTS.ROW_REMOVED, handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const dialogTitle = useMemo((): string => {
    const modeMap: Record<DialogMode, string> = {
      add: t('crudPage.dialogTitleAdd'),
      edit: t('crudPage.dialogTitleEdit'),
      view: t('crudPage.dialogTitleView'),
    };
    return modeMap[dialogMode] ?? 'Nuevo';
  }, [dialogMode, t]);

  /** Bridge between DxDataGrid's SelectionChangedEvent and our onSelectionChanged.
   * Also captures the selected row ID for the audit trail. */
  const handleSelectionChanged = (e: SelectionChangedEvent) => {
    selectionState.onSelectionChanged((e.selectedRowsData ?? []) as Record<string, unknown>[]);
    if (hasAuditTrail) {
      const first = (e.selectedRowsData ?? [])[0] as Record<string, unknown> | undefined;
      if (first != null) {
        const id = (first['id'] ?? first['ID'] ?? null) as string | number | null;
        setSelectedRowId(id);
      }
    }
  };

  const auditUrl = useMemo(() => {
    if (!resolvedResource.auditTrail?.enabled || selectedRowId == null) return null;
    const { apiUrl } = resolvedResource.auditTrail;
    return typeof apiUrl === 'function' ? apiUrl(selectedRowId) : `${apiUrl}${selectedRowId}`;
  }, [resolvedResource.auditTrail, selectedRowId]);

  const executeBulkAction = useCallback(
    async (action: BulkAction) => {
      await action.onAction(selectionState.selectedIds);
      selectionState.clearSelection();
      if (events.SUCCESS) {
        dispatch(events.SUCCESS);
      }
    },
    [selectionState, events],
  );

  const handleBulkAction = useCallback(
    (action: BulkAction) => {
      if (action.confirmMessage) {
        setConfirmState({ open: true, action });
      } else {
        void executeBulkAction(action);
      }
    },
    [executeBulkAction],
  );

  /** Preset selector rendered as a toolbar slot via `beforeToolbar`. */
  const renderPresetSelector = hasPresets
    ? () => (
        <div
          className="column-preset-selector"
          style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}
        >
          <label htmlFor={`preset-${resolvedResource.id}`} style={{ fontSize: 13 }}>
            {t('crudPage.columnsLabel')}
          </label>
          <select
            id={`preset-${resolvedResource.id}`}
            aria-label="Seleccionar preset de columnas"
            value={presetState.activePreset ?? ''}
            onChange={(e) => presetState.setPreset(e.target.value || null)}
            style={{ fontSize: 13 }}
          >
            <option value="">{t('crudPage.allColumns')}</option>
            {resolvedResource.columnPresets!.map((p) => (
              <option key={p.key} value={p.key}>
                {p.label}
              </option>
            ))}
          </select>
        </div>
      )
    : undefined;

  return (
    <div id="wrapper" className={auditOpen ? 'wrapper--with-audit' : undefined}>
      {(resolvedResource.bulkActions?.length ?? 0) > 0 && selectionState.hasSelection && (
        <div className="bulk-toolbar" role="toolbar" aria-label="Acciones en lote">
          <span className="bulk-toolbar__count">
            {t('crudPage.selectedCount', { count: selectionState.selectedCount })}
          </span>
          {resolvedResource.bulkActions!.map((action: BulkAction) => (
            <Button
              key={action.key}
              text={action.label}
              icon={action.icon}
              stylingMode="outlined"
              type="normal"
              onClick={() => handleBulkAction(action)}
            />
          ))}
        </div>
      )}
      {hasAuditTrail && (
        <div className="audit-trail-toolbar" style={{ marginBottom: 8 }}>
          <button
            type="button"
            onClick={() => setAuditOpen((prev) => !prev)}
            aria-pressed={auditOpen}
            aria-label="Mostrar historial de cambios"
          >
            {t('crudPage.auditTrailButton')}
          </button>
        </div>
      )}
      <DxDataGrid
        id={resolvedResource.id}
        title={resolvedResource.title}
        url={resolvedResource.apiUrl}
        fields={routeAwareGridFields}
        events={events}
        allowAdd={permissions.canAdd}
        allowEdit={permissions.canEdit}
        allowDelete={permissions.canDelete}
        allowExport={permissions.canExport}
        sort={resolvedResource.sort}
        filter={routeAwareFilters}
        paging={resolvedResource.paging}
        mode={resolvedResource.mode}
        stateStoringEnabled={resolvedResource.stateStoring}
        toolbar={resolvedResource.toolbar}
        selectionMode={hasMultipleSelection ? 'multiple' : 'single'}
        onSelectionChanged={handleSelectionChanged}
        editMode={resolvedResource.editMode}
        visibleColumns={presetState.visibleColumns}
        beforeToolbar={renderPresetSelector}
        detailUrl={resolvedResource.detailUrl}
        detailFields={resolvedResource.detailFields}
        onFilterChange={onFiltersChange}
      />
      {!isInlineEditMode && (
        <DxDialog
          events={events}
          width={resolvedResource.dialogWidth}
          height={resolvedResource.dialogHeight}
          title={dialogTitle}
          isOpen={dialogIsOpen}
          onClose={closeDialog}
        >
          <DxForm
            ref={formRef}
            url={resolvedResource.apiUrl}
            fields={fields}
            events={events}
            detailFields={resolvedResource.detailFields}
            detailUrl={resolvedResource.detailUrl}
            detailPropertyName={resolvedResource.detailPropertyName}
            format={resolvedResource.format}
            onSaveSuccess={resolvedResource.onSaveSuccess}
            onSaveError={resolvedResource.onSaveError}
            onDeleteSuccess={resolvedResource.onDeleteSuccess}
            onDeleteError={resolvedResource.onDeleteError}
            formLayout={resolvedResource.formLayout}
            computedValues={computedValues}
            onFieldDataChanged={onFormDataChange}
          />
        </DxDialog>
      )}
      {hasAuditTrail && (
        <AuditTrailPanel
          url={auditUrl}
          renderEntry={resolvedResource.auditTrail!.renderEntry}
          visible={auditOpen}
          onClose={() => setAuditOpen(false)}
        />
      )}
      <ConfirmDialog
        open={confirmState.open}
        title="Confirmar acción"
        message={confirmState.action?.confirmMessage ?? ''}
        variant="warning"
        onConfirm={() => {
          const action = confirmState.action;
          setConfirmState({ open: false, action: null });
          if (action) void executeBulkAction(action);
        }}
        onCancel={() => setConfirmState({ open: false, action: null })}
      />
    </div>
  );
};
