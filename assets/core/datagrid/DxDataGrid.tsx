import './DxDataGrid.scss';
import {
  ColumnChooser,
  ColumnFixing,
  DataGridRef,
  DataGridTypes,
  Editing,
  Export,
  FilterRow,
  HeaderFilter,
  Item,
  ITotalItemProps,
  LoadPanel,
  MasterDetail,
  Pager,
  Paging,
  Scrolling,
  Selection,
  Sorting,
  StateStoring,
  Summary,
  Toolbar,
} from 'devextreme-react/data-grid';
import { Button, DataGrid } from 'devextreme-react';
import {
  forwardRef,
  ReactNode,
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';
import { useNavigate } from 'react-router-dom';
import { dxUtils } from '../../shared/data/DxUtils';
import { EXPORT_FORMATS, handleDataGridExport } from './DxDataGridExport';
import { buildColumns, buildSummary, buildToolbar, getIdField } from './DxDataGridUtils';
import { DxDetailDataGrid } from './DxDetailDataGrid';
import { DxFilterRule } from '../field/FilterRule';
import { useEvents } from '../event/EventHook';
import toast from '@shared/lib/SharedUtils';
import { useCoreTranslation } from '../i18n/useCoreTranslation';
import { Field } from '../field/Field';
import { DataGridEventNames, ToolbarButtonItem } from '../event/EventTypes';
import { Store } from 'devextreme/data';
import { RowPreparedEvent, SelectionChangedEvent } from 'devextreme/ui/data_grid';

export interface DxDataGridOptions {
  id: string;
  title: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[] | ((parentRow: Record<string, unknown>) => Field[]);
  columns?: DataGridTypes.Column[];
  toolbar?: ToolbarButtonItem[] | ToolbarButtonItem[][];
  events?: DataGridEventNames;
  allowAdd?: boolean;
  allowEdit?: boolean;
  allowDelete?: boolean;
  allowExport?: boolean;
  addDisabled?: boolean;
  editDisabled?: boolean;
  deleteDisabled?: boolean;
  summaryFields?: ITotalItemProps[];
  filter?: DxFilterRule[];
  sort?: Array<{ selector: string; desc: boolean }>;
  mode?: 'minimal' | 'normal' | 'full';
  paging?: boolean;
  hasBackButton?: boolean;
  columnsHidingEnabled?: boolean;
  stateStoringEnabled?: boolean;
  toolbarVisible?: boolean;
  selectionMode?: 'single' | 'multiple';
  onBack?: () => void;
  onSelectionChanged?: (e: SelectionChangedEvent) => void;
  onRowPrepared?: (e: RowPreparedEvent) => void;
  onContentReady?: () => void;
  onFilterChange?: (filters: Record<string, string>) => void;
  filterRow?: boolean;
  headerFilter?: boolean;
  manualLoad?: boolean;
  beforeToolbar?: () => ReactNode;
  /**
   * DevExtreme inline editing mode.
   * - `'popup'` (default) — grid delegates editing to the external DxDialog.
   * - `'row'` | `'cell'` | `'batch'` — DevExtreme inline editing enabled within the grid.
   */
  editMode?: 'popup' | 'row' | 'cell' | 'batch';
  /**
   * When provided as a non-null array, only columns whose `dataField` matches an entry in this
   * list will be visible. When `null` or `undefined`, all columns are shown (existing behaviour).
   */
  visibleColumns?: string[] | null;
}

export interface DxDataGridEvents {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refresh: () => void;
  reset: () => void;
  getVisibleRows: () => DataGridTypes.Row[] | undefined;
  getSelectedRowKey: () => string | number | undefined;
  getSelectedRow: () => Record<string, unknown> | undefined;
  getSelectedRowKeys: () => unknown[];
  getSelectedRows: () => Record<string, unknown>[];
  getDataSource: () => Store | null;
  setDataSource: (dataSource: Store | null) => void;
  getFilter(): unknown[];
  filter(filterRule: DxFilterRule | null): void;
}

export const DATA_GRID_EVENTS = {
  SELECTION_CHANGED: 'datagrid:selection.changed',
  TOOLBAR_EVENT: 'datagrid:toolbar.event',
};

function extractFilterRulesFromExpression(
  expression: unknown,
): Array<{ field: string; operator: string; value: string }> {
  if (!Array.isArray(expression)) {
    return [];
  }

  if (
    expression.length === 3 &&
    typeof expression[0] === 'string' &&
    typeof expression[1] === 'string' &&
    !Array.isArray(expression[2])
  ) {
    return [{ field: expression[0], operator: expression[1], value: String(expression[2] ?? '') }];
  }

  return expression.flatMap((part) => extractFilterRulesFromExpression(part));
}

export const DxDataGrid = forwardRef<DxDataGridEvents, DxDataGridOptions>((options, ref) => {
  const { t } = useCoreTranslation();
  const gridRef = useRef<DataGridRef | null>(null);
  const [data, setData] = useState<Store | null>(null);
  const [isGridLoading, setIsGridLoading] = useState(!options.manualLoad);
  const viewMode = options.mode ?? 'full';
  const [on, emit] = useEvents();
  const navigate = useNavigate();
  const noDataText = t('grid.noRecords');
  const { onFilterChange } = options;

  const refresh = () => {
    gridRef.current?.instance().refresh();
  };

  const reset = () => {
    gridRef.current?.instance().clearSelection();
    gridRef.current?.instance().deselectAll();
    gridRef.current?.instance().clearSorting();
    gridRef.current?.instance().clearFilter();
    gridRef.current?.instance().resetOption('focusedRowKey');
  };

  const onExporting = (e: DataGridTypes.ExportingEvent) => handleDataGridExport(e, options.title);

  const onAddClick = () => {
    if (options.events?.ADD) emit(options.events?.ADD);
  };

  const onEditClick = () => {
    const selectedRowsData = gridRef.current?.instance().getSelectedRowsData();
    if (selectedRowsData && selectedRowsData.length > 0) {
      if (options.events?.EDIT) emit(options.events?.EDIT, { row: selectedRowsData[0] });
    } else {
      toast(t('grid.selectRequired'), 'warning', 2000);
    }
  };

  const onDeleteClick = () => {
    const selectedRowsData = gridRef.current?.instance().getSelectedRowsData();
    if (selectedRowsData && selectedRowsData.length > 0) {
      if (options.events?.DELETE) emit(options.events?.DELETE, { row: selectedRowsData[0] });
    } else {
      toast(t('grid.selectRequired'), 'warning', 2000);
    }
  };

  const setupObservers = () => {
    if (options.events?.SUCCESS) {
      on(options.events?.SUCCESS, refresh);
    }
  };

  const setupObserversRef = useRef(setupObservers);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  setupObserversRef.current = setupObservers;
  useEffect(() => {
    setupObserversRef.current();
  }, []);

  useEffect(() => {
    if (options.manualLoad) {
      setIsGridLoading(false);
      return;
    }

    setIsGridLoading(true);
    const newDataStore = dxUtils.getDataStore(
      options.url,
      getIdField(options.fields),
      options.url,
      options.filter,
      options.sort,
    );
    setData(newDataStore);
  }, [options.fields, options.filter, options.manualLoad, options.sort, options.url]);

  useEffect(() => {
    const dataSource = gridRef.current?.instance().getDataSource() as
      | {
          isLoading?: () => boolean;
          on?: (eventName: string, handler: (loading: boolean) => void) => void;
          off?: (eventName: string, handler: (loading: boolean) => void) => void;
        }
      | undefined;

    if (!dataSource) {
      return;
    }

    const handleLoadingChanged = (loading: boolean) => {
      setIsGridLoading(loading);
    };

    setIsGridLoading(dataSource.isLoading?.() ?? false);
    dataSource.on?.('loadingChanged', handleLoadingChanged);

    return () => {
      dataSource.off?.('loadingChanged', handleLoadingChanged);
    };
  }, [data]);

  const renderDetail = useCallback(
    (e: { key: unknown; data: Record<string, unknown> }) => {
      const detailUrl = options.detailUrl?.replace('{id}', String(e.key));
      const fields =
        typeof options.detailFields === 'function'
          ? options.detailFields(e.data)
          : options.detailFields;

      return (
        <DxDetailDataGrid
          options={{
            url: detailUrl,
            fields,
          }}
        />
      );
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [options.detailUrl, options.detailFields],
  );

  const showLoading = (message?: string) => {
    setIsGridLoading(true);
    gridRef.current?.instance().beginCustomLoading(message ?? t('grid.loading'));
  };

  const hideLoading = () => {
    setIsGridLoading(false);
    gridRef.current?.instance().endCustomLoading();
  };

  const getVisibleRows = () => {
    return gridRef.current?.instance().getVisibleRows();
  };

  const getSelectedRowKey = () => {
    const key = gridRef.current?.instance().getSelectedRowKeys()[0];
    return typeof key === 'string' || typeof key === 'number' ? key : undefined;
  };

  const getSelectedRow = () => {
    return gridRef.current?.instance().getSelectedRowsData()[0];
  };

  const getSelectedRowKeys = () => {
    return gridRef.current?.instance().getSelectedRowKeys() ?? [];
  };

  const getSelectedRows = () => {
    return (gridRef.current?.instance().getSelectedRowsData() ?? []) as Record<string, unknown>[];
  };

  const setDataSource = (dataSource: Store | null) => {
    setData(dataSource);
  };

  const getDataSource = () => {
    return data;
  };

  const getFilter = () => {
    const filter = gridRef.current?.instance().getCombinedFilter();

    if (Array.isArray(filter) && filter.length === 3 && !(typeof filter[0] === 'object')) {
      return [filter];
    }

    return filter ?? [];
  };

  const filter = (filterRule: DxFilterRule | null) => {
    if (filterRule) {
      gridRef.current?.instance().filter([filterRule.field, filterRule.operator, filterRule.value]);
    } else {
      gridRef.current?.instance().filter(filterRule);
    }
  };

  const onSelectionChanged = (e: SelectionChangedEvent) => {
    emit(DATA_GRID_EVENTS.SELECTION_CHANGED, e.selectedRowsData);
  };

  const handleOptionChanged = useCallback(
    (e: { fullName?: string; component?: { getCombinedFilter?: () => unknown } }) => {
      if (!onFilterChange || !e.fullName?.includes('filter')) {
        return;
      }

      const rules = extractFilterRulesFromExpression(e.component?.getCombinedFilter?.());
      const nextFilters = rules.reduce<Record<string, string>>((acc, rule) => {
        if (rule.value !== '') {
          acc[rule.field] = rule.value;
        }
        return acc;
      }, {});

      onFilterChange(nextFilters);
    },
    [onFilterChange],
  );

  /**
   * Apply column-preset visibility whenever `visibleColumns` changes.
   * When the prop is a non-null array we iterate over all columns and toggle their `visible`
   * option based on whether the column's dataField is included in the list.
   * When it is null/undefined we restore all columns to visible (no restriction).
   */
  useEffect(() => {
    const instance = gridRef.current?.instance();
    if (!instance) return;
    const cols = instance.getVisibleColumns();
    if (options.visibleColumns != null) {
      cols.forEach((col) => {
        const field = col.dataField;
        if (field) {
          instance.columnOption(field, 'visible', options.visibleColumns!.includes(field));
        }
      });
    } else {
      // Restore visibility for all field columns
      cols.forEach((col) => {
        if (col.dataField) {
          instance.columnOption(col.dataField, 'visible', true);
        }
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options.visibleColumns]);

  useImperativeHandle(ref, () => ({
    showLoading,
    hideLoading,
    refresh,
    reset,
    getVisibleRows,
    getSelectedRowKey,
    getSelectedRow,
    getSelectedRowKeys,
    getSelectedRows,
    getDataSource,
    setDataSource,
    getFilter,
    filter,
  }));

  return (
    <div className="view datagrid-list">
      <div
        className={
          'view-wrapper ' + ('full' === viewMode ? 'view-wrapper-datagrid-list list-page' : '')
        }
      >
        <DataGrid
          ref={gridRef}
          className="grid theme-dependent"
          noDataText={isGridLoading ? '' : noDataText}
          focusedRowEnabled
          keyExpr={getIdField(options.fields) || undefined}
          height="100%"
          width="100%"
          dataSource={data}
          allowColumnReordering
          showBorders={true}
          rowAlternationEnabled={true}
          hoverStateEnabled={true}
          remoteOperations={options.paging ?? true}
          syncLookupFilterValues={false}
          onSelectionChanged={options.onSelectionChanged ?? onSelectionChanged}
          onRowPrepared={options.onRowPrepared}
          onContentReady={options.onContentReady}
          onOptionChanged={handleOptionChanged}
          columnHidingEnabled={options.columnsHidingEnabled ?? false}
          allowColumnResizing={true}
          columnResizingMode={'widget'}
          columnMinWidth={50}
          columnAutoWidth={true}
          onExporting={onExporting}
          dateSerializationFormat={'yyyy-MM-dd'}
        >
          <LoadPanel showPane={false} />
          <ColumnChooser enabled mode="select" />
          <ColumnFixing enabled={true} />
          <Export
            enabled={options.allowExport ?? false}
            allowExportSelectedData
            formats={EXPORT_FORMATS}
          />
          <Selection
            mode={options.selectionMode ?? 'single'}
            selectAllMode="page"
            showCheckBoxesMode="onClick"
          />
          <Sorting mode="multiple" />
          <Scrolling mode="standard" />
          <Toolbar visible={(options.toolbarVisible ?? true) && 'minimal' !== viewMode}>
            <Item location="before" visible={options.hasBackButton ?? false}>
              <Button
                icon="ph ph-arrow-left"
                stylingMode="text"
                onClick={() => {
                  if (options.onBack) {
                    options.onBack();
                  } else {
                    navigate(-1);
                  }
                }}
              />
            </Item>
            <Item location="before" visible={'full' === viewMode}>
              <div className="grid-header">{options.title}</div>
            </Item>
            {options.beforeToolbar && options.beforeToolbar()}
            <Item location="after" locateInMenu="auto" visible={options.allowAdd ?? false}>
              <Button
                text={t('grid.buttonNew')}
                type="default"
                stylingMode="contained"
                onClick={onAddClick}
                disabled={options.addDisabled ?? false}
              />
            </Item>
            <Item location="after" locateInMenu="auto" visible={options.allowEdit ?? false}>
              <Button
                text={t('grid.buttonEdit')}
                type="normal"
                className="dx-button-warning"
                stylingMode="outlined"
                onClick={onEditClick}
                disabled={options.editDisabled ?? false}
              />
            </Item>
            <Item location="after" locateInMenu="auto" visible={options.allowDelete ?? false}>
              <Button
                text={t('grid.buttonDelete')}
                type="danger"
                stylingMode="outlined"
                onClick={onDeleteClick}
                disabled={options.deleteDisabled ?? false}
              />
            </Item>
            {buildToolbar(options.toolbar)}
            <Item location="after" locateInMenu="auto" showText="inMenu" widget="dxButton">
              <Button
                icon="ph ph-arrow-clockwise"
                text={t('grid.buttonRefresh')}
                stylingMode="text"
                onClick={refresh}
              />
            </Item>
            {options.allowExport && (
              <Item location="after" locateInMenu="auto">
                <div className="separator" />
              </Item>
            )}
            <Item name="exportButton" />
            <Item location="after" locateInMenu="auto">
              <div className="separator" />
            </Item>
            <Item name="columnChooserButton" locateInMenu="auto" />
          </Toolbar>
          <FilterRow visible={options.filterRow ?? true} />
          <HeaderFilter visible={options.headerFilter ?? false} />
          <StateStoring
            enabled={options.stateStoringEnabled ?? false}
            type="localStorage"
            storageKey={'datagrid-' + options.id}
          />
          {(options.editMode === 'row' ||
            options.editMode === 'cell' ||
            options.editMode === 'batch') && (
            <Editing
              mode={options.editMode}
              allowUpdating={options.allowEdit ?? true}
              allowDeleting={options.allowDelete ?? true}
              allowAdding={options.allowAdd ?? true}
            />
          )}
          {buildColumns(options.fields)}
          <Summary>{buildSummary(options.summaryFields)}</Summary>
          <MasterDetail enabled={!!options.detailFields} render={renderDetail} />
          <Paging defaultPageSize={20} />
          <Pager
            visible={options.paging ?? true}
            allowedPageSizes={[10, 20, 50, 100]}
            displayMode={'compact'}
            showPageSizeSelector={true}
            showInfo={true}
            showNavigationButtons={true}
          />
        </DataGrid>
      </div>
    </div>
  );
});

DxDataGrid.displayName = 'DxDataGrid';
