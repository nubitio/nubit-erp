import React, { useMemo, useState } from 'react';
import { Button } from 'devextreme-react';
import type { SelectionChangedEvent } from 'devextreme/ui/data_grid';
import { DxDataGrid } from '../datagrid/DxDataGrid';
import { DxDialog } from '../dialog/DxDialog';
import { DxForm } from '../form/DxForm';
import { useCrudPage } from './useCrudPage';
import { dispatch, useEvents } from '../event/EventHook';
import type { ResourceConfig } from './ResourceConfig';
import type { BulkAction } from './BulkAction';
import { AuditTrailPanel } from './AuditTrailPanel';
import { useCoreTranslation } from '../i18n/useCoreTranslation';
import { DialogStoreProvider } from './DialogStoreProvider';
import { useCrudDialogStore } from './useCrudDialogStore';
import type { DialogMode } from './dialogStore';

interface CrudPageProps<T extends Record<string, unknown> = Record<string, unknown>> {
  resource: ResourceConfig<T>;
}

/**
 * Public export — wraps CrudPageInner in its own DialogStoreProvider so that
 * existing pages do not need to add a provider themselves.
 */
export const CrudPage = <T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
}: CrudPageProps<T>) => (
  <DialogStoreProvider>
    <CrudPageInner resource={resource} />
  </DialogStoreProvider>
);

const CrudPageInner = <T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
}: CrudPageProps<T>) => {
  const { t } = useCoreTranslation();
  const { events, fields, formRef, permissions, selectionState, presetState } =
    useCrudPage(resource);

  const isInlineEditMode =
    resource.editMode === 'row' || resource.editMode === 'cell' || resource.editMode === 'batch';

  const hasMultipleSelection = (resource.bulkActions?.length ?? 0) > 0;
  const hasPresets = (resource.columnPresets?.length ?? 0) > 0;
  const hasAuditTrail = resource.auditTrail?.enabled === true;

  const [selectedRowId, setSelectedRowId] = useState<string | number | null>(null);
  const [auditOpen, setAuditOpen] = useState(false);

  // Dialog state managed via the store
  const {
    isOpen: dialogIsOpen,
    mode: dialogMode,
    openDialog,
    closeDialog,
  } = useCrudDialogStore(resource.id);

  // Wire RxJS event bus → dialog store
  const [on] = useEvents();

  // Subscribe to ADD / EDIT / SUCCESS events and mirror them into the store.
  // We use a ref-based pattern (same as DxDialog) so the effect runs once.
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

  // Derive dialog title from mode
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
    if (!resource.auditTrail?.enabled || selectedRowId == null) return null;
    const { apiUrl } = resource.auditTrail;
    return typeof apiUrl === 'function' ? apiUrl(selectedRowId) : `${apiUrl}${selectedRowId}`;
  }, [resource.auditTrail, selectedRowId]);

  const handleBulkAction = async (action: BulkAction) => {
    if (action.confirmMessage) {
      // eslint-disable-next-line no-alert
      const confirmed = window.confirm(action.confirmMessage);
      if (!confirmed) return;
    }
    await action.onAction(selectionState.selectedIds);
    selectionState.clearSelection();
    if (events.SUCCESS) {
      dispatch(events.SUCCESS);
    }
  };

  /** Preset selector rendered as a toolbar slot via `beforeToolbar`. */
  const renderPresetSelector = hasPresets
    ? () => (
        <div
          className="column-preset-selector"
          style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}
        >
          <label htmlFor={`preset-${resource.id}`} style={{ fontSize: 13 }}>
            {t('crudPage.columnsLabel')}
          </label>
          <select
            id={`preset-${resource.id}`}
            aria-label="Seleccionar preset de columnas"
            value={presetState.activePreset ?? ''}
            onChange={(e) => presetState.setPreset(e.target.value || null)}
            style={{ fontSize: 13 }}
          >
            <option value="">{t('crudPage.allColumns')}</option>
            {resource.columnPresets!.map((p) => (
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
      {(resource.bulkActions?.length ?? 0) > 0 && selectionState.hasSelection && (
        <div className="bulk-toolbar" role="toolbar" aria-label="Acciones en lote">
          <span className="bulk-toolbar__count">
            {t('crudPage.selectedCount', { count: selectionState.selectedCount })}
          </span>
          {resource.bulkActions!.map((action: BulkAction) => (
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
        id={resource.id}
        title={resource.title}
        url={resource.apiUrl}
        fields={fields}
        events={events}
        allowAdd={permissions.canAdd}
        allowEdit={permissions.canEdit}
        allowDelete={permissions.canDelete}
        allowExport={permissions.canExport}
        sort={resource.sort}
        filter={resource.filter}
        paging={resource.paging}
        mode={resource.mode}
        stateStoringEnabled={resource.stateStoring}
        toolbar={resource.toolbar}
        selectionMode={hasMultipleSelection ? 'multiple' : 'single'}
        onSelectionChanged={handleSelectionChanged}
        editMode={resource.editMode}
        visibleColumns={presetState.visibleColumns}
        beforeToolbar={renderPresetSelector}
      />
      {!isInlineEditMode && (
        <DxDialog
          events={events}
          width={resource.dialogWidth}
          height={resource.dialogHeight}
          title={dialogTitle}
          isOpen={dialogIsOpen}
          onClose={closeDialog}
        >
          <DxForm
            ref={formRef}
            url={resource.apiUrl}
            fields={fields}
            events={events}
            detailFields={resource.detailFields}
            detailUrl={resource.detailUrl}
            detailPropertyName={resource.detailPropertyName}
            format={resource.format}
            onSaveSuccess={resource.onSaveSuccess}
            onSaveError={resource.onSaveError}
            onDeleteSuccess={resource.onDeleteSuccess}
            onDeleteError={resource.onDeleteError}
            formLayout={resource.formLayout}
          />
        </DxDialog>
      )}
      {hasAuditTrail && (
        <AuditTrailPanel
          url={auditUrl}
          renderEntry={resource.auditTrail!.renderEntry}
          visible={auditOpen}
          onClose={() => setAuditOpen(false)}
        />
      )}
    </div>
  );
};
