import './DxDataGrid.scss';
import {
  ColumnChooser,
  ColumnFixing,
  DataGridRef,
  DataGridTypes,
  Export,
  FilterRow,
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
import { jsPDF as JsPdf } from 'jspdf';
import { saveAs } from 'file-saver';
import { Workbook } from 'exceljs';
import { exportDataGrid as exportDataGridToPdf } from 'devextreme/pdf_exporter';
import { exportDataGrid as exportDataGridToXLSX } from 'devextreme/excel_exporter';
import { dxUtils } from '../../shared/data/DxUtils';
import { buildColumns, buildSummary, buildToolbar, getIdField } from './DxDataGridUtils';
import { DxDetailDataGrid } from './DxDetailDataGrid';
import { DxFilterRule } from '../common/FilterRule';
import { useEvents } from '../event/EventHook';
import toast from '../utils/SharedUtils';
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
  detailFields?: Field[] | ((parentRow: any) => Field[]);
  columns?: any[];
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
  sort?: any[];
  mode?: 'minimal' | 'normal' | 'full';
  paging?: boolean;
  hasBackButton?: boolean;
  columnsHidingEnabled?: boolean;
  stateStoringEnabled?: boolean;
  toolbarVisible?: boolean;
  selectionMode?: 'single' | 'multiple';
  onSelectionChanged?: (e: SelectionChangedEvent) => void;
  onRowPrepared?: (e: RowPreparedEvent) => void;
  onContentReady?: () => void;
  filterRow?: boolean;
  manualLoad?: boolean;
  beforeToolbar?: () => ReactNode;
}

export interface DxDataGridEvents {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refresh: () => void;
  reset: () => void;
  getVisibleRows: () => any;
  getSelectedRowKey: () => any;
  getSelectedRow: () => any;
  getSelectedRowKeys: () => any;
  getSelectedRows: () => any;
  getDataSource: () => Store | null;
  setDataSource: (dataSource: Store | null) => void;

  getFilter(): any[];

  filter(filterRule: DxFilterRule | null): void;
}

export const DATA_GRID_EVENTS = {
  SELECTION_CHANGED: 'datagrid:selection.changed',
  TOOLBAR_EVENT: 'datagrid:toolbar.event',
};

const EXPORT_FORMATS = ['xlsx', 'pdf'];

export const DxDataGrid = forwardRef<DxDataGridEvents, DxDataGridOptions>((options, ref) => {
  const gridRef = useRef<DataGridRef | null>(null);
  const [data, setData] = useState<Store | null>(null);
  const viewMode = options.mode ?? 'full';
  const [on, emit] = useEvents();
  const noDataText = 'No hay registros para mostrar';

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

  const onExporting = (e: DataGridTypes.ExportingEvent) => {
    if (e.format === 'pdf') {
      const doc = new JsPdf();
      exportDataGridToPdf({
        jsPDFDocument: doc,
        component: e.component,
      }).then(() => {
        doc.save(options.title + '.pdf');
      });
    } else {
      const workbook = new Workbook();
      const worksheet = workbook.addWorksheet(options.title);

      exportDataGridToXLSX({
        component: e.component,
        worksheet,
        autoFilterEnabled: true,
      }).then(() => {
        workbook.xlsx.writeBuffer().then((buffer) => {
          saveAs(new Blob([buffer], { type: 'application/octet-stream' }), options.title + '.xlsx');
        });
      });
      e.cancel = true;
    }
  };

  const onAddClick = () => {
    if (options.events?.ADD) emit(options.events?.ADD);
  };

  const onEditClick = () => {
    const selectedRowsData = gridRef.current?.instance().getSelectedRowsData();
    if (selectedRowsData && selectedRowsData.length > 0) {
      if (options.events?.EDIT) emit(options.events?.EDIT, { row: selectedRowsData[0] });
    } else {
      toast('Debe seleccionar un registro', 'warning', 2000);
    }
  };

  const onDeleteClick = () => {
    const selectedRowsData = gridRef.current?.instance().getSelectedRowsData();
    if (selectedRowsData && selectedRowsData.length > 0) {
      if (options.events?.DELETE) emit(options.events?.DELETE, { row: selectedRowsData[0] });
    } else {
      toast('Debe seleccionar un registro', 'warning', 2000);
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
  const initialOptionsRef = useRef(options);

  useEffect(() => {
    setupObserversRef.current();
    const initialOptions = initialOptionsRef.current;
    if (initialOptions.manualLoad) {
      return;
    }
    const newDataStore = dxUtils.getDataStore(
      initialOptions.url,
      getIdField(initialOptions.fields),
      initialOptions.url,
      initialOptions.filter,
      initialOptions.sort,
    );
    setData(newDataStore);
  }, []);

  const renderDetail = useCallback(
    (e: any) => {
      const detailUrl = options.detailUrl?.replace('{id}', e.key);
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

  const showLoading = (message: string = 'Cargando...') => {
    gridRef.current?.instance().beginCustomLoading(message);
  };

  const hideLoading = () => {
    gridRef.current?.instance().endCustomLoading();
  };

  const getVisibleRows = () => {
    return gridRef.current?.instance().getVisibleRows();
  };

  const getSelectedRowKey = () => {
    return gridRef.current?.instance().getSelectedRowKeys()[0];
  };

  const getSelectedRow = () => {
    return gridRef.current?.instance().getSelectedRowsData()[0];
  };

  const getSelectedRowKeys = () => {
    return gridRef.current?.instance().getSelectedRowKeys();
  };

  const getSelectedRows = () => {
    return gridRef.current?.instance().getSelectedRowsData();
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
          noDataText={noDataText}
          focusedRowEnabled
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
                  window.history.back();
                }}
              />
            </Item>
            <Item location="before" visible={'full' === viewMode}>
              <div className="grid-header">{options.title}</div>
            </Item>
            {options.beforeToolbar && options.beforeToolbar()}
            <Item location="after" locateInMenu="auto" visible={options.allowAdd ?? false}>
              <Button
                text="Nuevo"
                type="default"
                stylingMode="outlined"
                onClick={onAddClick}
                disabled={options.addDisabled ?? false}
              />
            </Item>
            <Item location="after" locateInMenu="auto" visible={options.allowEdit ?? false}>
              <Button
                text="Editar"
                type="normal"
                className="dx-button-warning"
                stylingMode="outlined"
                onClick={onEditClick}
                disabled={options.editDisabled ?? false}
              />
            </Item>
            <Item location="after" locateInMenu="auto" visible={options.allowDelete ?? false}>
              <Button
                text="Eliminar"
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
                text="Actualizar"
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
          <StateStoring
            enabled={options.stateStoringEnabled ?? false}
            type="localStorage"
            storageKey={'datagrid-' + options.id}
          />
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
