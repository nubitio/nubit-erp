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
    Toolbar
} from "devextreme-react/data-grid";
import {Button, DataGrid} from "devextreme-react";
import {forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState} from "react";
import {jsPDF as JsPdf} from 'jspdf';
import {saveAs} from 'file-saver';
import {Workbook} from 'exceljs';
import {exportDataGrid as exportDataGridToPdf} from 'devextreme/pdf_exporter';
import {exportDataGrid as exportDataGridToXLSX} from 'devextreme/excel_exporter';
import {dxUtils} from "../common/DxUtils";
import CustomStore from "devextreme/data/custom_store";
import {buildColumns, buildSummary, buildToolbar, getIdField} from "./DxDataGridUtils";
import {DxDetailDataGrid} from "./DxDetailDataGrid";
import {DxFilterRule} from "../common/FilterRule";
import {useEvents} from "../event/EventHook";
import DevExpress from "devextreme";
import toast from "../utils/SharedUtils";
import SelectionChangedEvent = DevExpress.ui.dxDataGrid.SelectionChangedEvent;

export interface DxDataGridOptions {
    id: string;
    title: string;
    url: string;
    detailUrl?: string;
    fields: any;
    detailFields?: any;
    columns?: any[];
    toolbar?: any[];
    events?: any;
    allowAdd?: boolean;
    allowEdit?: boolean;
    allowDelete?: boolean;
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
}

export interface DxDataGridEvents {
    showLoading: (message?: string) => void;
    hideLoading: () => void;
    refresh: () => void;
    getSelectedRowKey: () => any;
    getSelectedRow: () => any;
    getSelectedRowKeys: () => any;
    getSelectedRows: () => any;
    getDataSource: () => CustomStore | null;
}

export const DATA_GRID_EVENTS = {
    SELECTION_CHANGED: 'datagrid:selection.changed',
}

export const DxDataGrid = forwardRef<DxDataGridEvents, DxDataGridOptions>((options, ref) => {
    const gridRef = useRef<DataGridRef | null>(null);
    const [data, setData] = useState<CustomStore | null>(null);
    const exportFormats = ['xlsx', 'pdf'];
    const viewMode = options.mode ?? 'full';
    const [on, emit] = useEvents()

    const refresh = () => {
        gridRef.current?.instance().refresh();
    }

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
                    saveAs(new Blob([buffer], {type: 'application/octet-stream'}), options.title + '.xlsx');
                });
            });
            e.cancel = true;
        }
    };

    const onAddClick = () => {
        options.events?.ADD &&
        emit(options.events?.ADD);
    }

    const onEditClick = () => {
        let selectedRowsData = gridRef.current?.instance().getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            options.events?.EDIT &&
            emit(options.events?.EDIT, {row: selectedRowsData[0]});
        } else {
            toast('Debe seleccionar un registro', 'warning', 2000)
        }
    }

    const onDeleteClick = () => {
        let selectedRowsData = gridRef.current?.instance().getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            options.events?.DELETE &&
            emit(options.events?.DELETE, {row: selectedRowsData[0]});
        } else {
            toast('Debe seleccionar un registro', 'warning', 2000)
        }
    }

    const setupObservers = () => {
        if (options.events?.SUCCESS) {
            on(options.events?.SUCCESS, refresh);
        }
    }

    useEffect(() => {
        setData(dxUtils.getDataStore(
            options.url,
            getIdField(options.fields),
            options.url,
            options.filter,
            options.sort
        ));
        setupObservers();
    }, []);

    const renderDetail = useCallback((e: any) => {
        let detailUrl = options.detailUrl?.replace('{id}', e.key);

        return <DxDetailDataGrid options={{
            url: detailUrl,
            fields: options.detailFields,
        }}/>
    }, [options.detailUrl, options.detailFields]);

    const showLoading = (message: string = 'Cargando...') => {
        gridRef.current?.instance().beginCustomLoading(message);
    }

    const hideLoading = () => {
        gridRef.current?.instance().endCustomLoading();
    }

    const getSelectedRowKey = () => {
        return gridRef.current?.instance().getSelectedRowKeys()[0];
    }

    const getSelectedRow = () => {
        return gridRef.current?.instance().getSelectedRowsData()[0];
    }

    const getSelectedRowKeys = () => {
        return gridRef.current?.instance().getSelectedRowKeys();
    }

    const getSelectedRows = () => {
        return gridRef.current?.instance().getSelectedRowsData();
    }

    const getDataSource = () => {
        return data;
    }

    const onSelectionChanged = (e: SelectionChangedEvent) => {
        emit(DATA_GRID_EVENTS.SELECTION_CHANGED, e.selectedRowsData);
    }

    useImperativeHandle(ref, () => ({
        showLoading,
        hideLoading,
        refresh,
        getSelectedRowKey,
        getSelectedRow,
        getSelectedRowKeys,
        getSelectedRows,
        getDataSource,
    }));

    return (
        <div className='view datagrid-list'>
            <div
                className={'view-wrapper ' + ('full' === viewMode ? 'view-wrapper-datagrid-list list-page' : '')}>
                <DataGrid
                    ref={gridRef}
                    className='grid theme-dependent'
                    noDataText=''
                    focusedRowEnabled
                    height='100%'
                    width='100%'
                    dataSource={data}
                    allowColumnReordering
                    showBorders={true}
                    remoteOperations={true}
                    syncLookupFilterValues={false}
                    onSelectionChanged={onSelectionChanged}
                    columnHidingEnabled={options.columnsHidingEnabled ?? false}
                    allowColumnResizing={true}
                    columnResizingMode={'widget'}
                    columnMinWidth={50}
                    columnAutoWidth={true}
                    onExporting={onExporting}
                >
                    <LoadPanel showPane={false}/>
                    <ColumnChooser enabled mode="select"/>
                    <ColumnFixing enabled={true}/>
                    <Export enabled allowExportSelectedData formats={exportFormats}/>
                    <Selection
                        mode={options.selectionMode ?? 'single'}
                        selectAllMode='page'
                        showCheckBoxesMode='onClick'
                    />
                    <Sorting mode='multiple'/>
                    <Scrolling mode='standard'/>
                    <Toolbar visible={(options.toolbarVisible ?? true) && 'minimal' !== viewMode}>
                        <Item location='before' visible={options.hasBackButton ?? false}>
                            <Button icon='arrowleft' onClick={() => {
                                window.history.back();
                            }}/>
                        </Item>
                        <Item location='before' visible={'full' === viewMode}>
                            <div className='grid-header'>{options.title}</div>
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowAdd ?? false}>
                            <Button
                                text='Nuevo'
                                type='default'
                                stylingMode='outlined'
                                onClick={onAddClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowEdit ?? false}>
                            <Button
                                text='Editar'
                                type='normal'
                                className='dx-button-warning'
                                stylingMode='outlined'
                                onClick={onEditClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowDelete ?? false}>
                            <Button
                                text='Eliminar'
                                type='danger'
                                stylingMode='outlined'
                                onClick={onDeleteClick}
                            />
                        </Item>
                        {buildToolbar(options.toolbar)}
                        <Item
                            location='after'
                            locateInMenu='auto'
                            showText='inMenu'
                            widget='dxButton'
                        >
                            <Button
                                icon='refresh'
                                text='Refresh'
                                stylingMode='text'
                                onClick={refresh}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto'>
                            <div className='separator'/>
                        </Item>
                        <Item name='exportButton'/>
                        <Item location='after' locateInMenu='auto'>
                            <div className='separator'/>
                        </Item>
                        <Item name='columnChooserButton' locateInMenu='auto'/>
                        <Item location='after' locateInMenu='auto'>
                            <div className='separator'/>
                        </Item>
                        <Item
                            location='after'
                            locateInMenu='auto'
                            showText='inMenu'
                            widget='dxButton'
                        >
                            <Button
                                icon='ph ph-circle-notch'
                                text='Restablecer'
                                stylingMode='text'
                                onClick={() => gridRef.current?.instance().state(null)}
                            />
                        </Item>
                    </Toolbar>
                    <FilterRow visible={true}/>
                    <StateStoring
                        enabled={options.stateStoringEnabled ?? true}
                        type="localStorage"
                        storageKey={"datagrid-" + options.id}
                    />
                    {buildColumns(options.fields)}
                    <Summary>
                        {buildSummary(options.summaryFields)}
                    </Summary>
                    <MasterDetail enabled={options.detailFields?.length > 0} render={renderDetail}/>
                    <Paging enabled={options.paging ?? true} defaultPageSize={20}/>
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
