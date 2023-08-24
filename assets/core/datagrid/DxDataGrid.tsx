import './DxDataGrid.scss';
import {
    ColumnChooser,
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
    Summary,
    Toolbar
} from "devextreme-react/data-grid";
import {Button, DataGrid} from "devextreme-react";
import {forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState} from "react";
import {jsPDF as JsPdf} from 'jspdf';
import {saveAs} from 'file-saver-es';
import {Workbook} from 'exceljs';
import {exportDataGrid as exportDataGridToPdf} from 'devextreme/pdf_exporter';
import {exportDataGrid as exportDataGridToXLSX} from 'devextreme/excel_exporter';
import {bus} from "../event/EventBus";
import notify from "devextreme/ui/notify";
import {dxUtils} from "../common/DxUtils";
import CustomStore from "devextreme/data/custom_store";
import {buildColumns, buildSummary, buildToolbar, getIdField} from "./DxDataGridUtils";
import {DxDetailDataGrid} from "./DxDetailDataGrid";
import {DxFilterRule} from "../common/FilterRule";

export interface DxDataGridOptions {
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
    mode?: 'simple' | 'full';
}

export interface DxDataGridEvents {
    showLoading: (message?: string) => void;
    hideLoading: () => void;
    refresh: () => void;
    getSelectedRowKey: () => any;
    getSelectedRow: () => any;
    getSelectedRowKeys: () => any;
    getSelectedRows: () => any;
}

export const DATA_GRID_EVENTS = {
    SELECTION_CHANGED: 'datagrid:selection.changed',
}

export const DxDataGrid = forwardRef<DxDataGridEvents, DxDataGridOptions>((options, ref) => {
    const gridRef = useRef<DataGrid>(null);
    const [data, setData] = useState<CustomStore | null>(null);
    const exportFormats = ['xlsx', 'pdf'];

    const refresh = useCallback(() => {
        gridRef.current?.instance.refresh();
    }, []);

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

    const onAddClick = useCallback(() => {
        options.events?.ADD &&
        bus.emit(options.events?.ADD);
    }, [options.events?.ADD]);

    const onEditClick = useCallback(() => {
        let selectedRowsData = gridRef.current?.instance.getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            options.events?.EDIT &&
            bus.emit(options.events?.EDIT, {row: selectedRowsData[0]});
        } else {
            notify('Debe seleccionar un registro', 'error', 2000);
        }
    }, [options.events?.EDIT]);

    const onDeleteClick = useCallback(() => {
        let selectedRowsData = gridRef.current?.instance.getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            options.events?.DELETE &&
            bus.emit(options.events?.DELETE, {row: selectedRowsData[0]});
        } else {
            notify('Debe seleccionar un registro', 'error', 2000);
        }
    }, [options.events?.DELETE]);

    const setupObservers = () => {
        if (options.events?.SUCCESS) bus.on(options.events?.SUCCESS, refresh);
    }

    let alreadyLoaded = false;
    let alreadySetup = false;

    useEffect(() => {
        if (!alreadyLoaded) {
            setData(dxUtils.getDataStore(
                options.url,
                getIdField(options.fields),
                options.url,
                options.filter,
                options.sort
            ));
            alreadyLoaded = true;
        }
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

    const renderDetail = useCallback((e: any) => {
        let detailUrl = options.detailUrl?.replace('{id}', e.key);

        return <DxDetailDataGrid options={{
            url: detailUrl,
            fields: options.detailFields,
        }}/>
    }, [options.detailUrl, options.detailFields]);

    const showLoading = (message: string = 'Cargando...') => {
        gridRef.current?.instance.beginCustomLoading(message);
    }

    const hideLoading = () => {
        gridRef.current?.instance.endCustomLoading();
    }

    const getSelectedRowKey = () => {
        return gridRef.current?.instance.getSelectedRowKeys()[0];
    }

    const getSelectedRow = () => {
        return gridRef.current?.instance.getSelectedRowsData()[0];
    }

    const getSelectedRowKeys = () => {
        return gridRef.current?.instance.getSelectedRowKeys();
    }

    const getSelectedRows = () => {
        return gridRef.current?.instance.getSelectedRowsData();
    }

    const onSelectionChanged = (e: any) => {
        bus.emit(DATA_GRID_EVENTS.SELECTION_CHANGED, e);
    }

    useImperativeHandle(ref, () => ({
        showLoading,
        hideLoading,
        refresh,
        getSelectedRowKey,
        getSelectedRow,
        getSelectedRowKeys,
        getSelectedRows,
    }));

    return (
        <div className='view datagrid-list'>
            <div className={'view-wrapper ' + ('full' === options.mode ?? 'full' ? 'view-wrapper-datagrid-list' : '')}>
                <DataGrid
                    ref={gridRef}
                    className='grid'
                    noDataText='No hay datos'
                    focusedRowEnabled
                    height='100%'
                    dataSource={data}
                    allowColumnReordering
                    remoteOperations={true}
                    syncLookupFilterValues={false}
                    onSelectionChanged={onSelectionChanged}
                    onExporting={onExporting}
                >
                    <LoadPanel showPane={false}/>
                    <ColumnChooser enabled/>
                    <Export enabled allowExportSelectedData formats={exportFormats}/>
                    <Selection
                        selectAllMode='allPages'
                        showCheckBoxesMode='onClick'
                        mode='multiple'
                    />
                    <Sorting mode='multiple'/>
                    <Scrolling mode='virtual'/>
                    <Toolbar visible={'full' === options.mode ?? 'full'}>
                        <Item location='before'>
                            <div className='grid-header'>{options.title}</div>
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowAdd ?? false}>
                            <Button
                                icon='plus'
                                text='Nuevo'
                                type='success'
                                stylingMode='outlined'
                                onClick={onAddClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowEdit ?? false}>
                            <Button
                                icon='edit'
                                text='Editar'
                                type='default'
                                stylingMode='outlined'
                                onClick={onEditClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowDelete ?? false}>
                            <Button
                                icon='trash'
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
                    </Toolbar>
                    <FilterRow visible={true}/>
                    {buildColumns(options.fields)}
                    <Summary>
                        {buildSummary(options.summaryFields)}
                    </Summary>
                    <MasterDetail enabled={options.detailFields?.length > 0} render={renderDetail}/>
                    <Paging defaultPageSize={20}/>
                    <Pager visible={true} showPageSizeSelector allowedPageSizes={[20, 50, 100]}/>
                </DataGrid>
            </div>
        </div>
    );
});

DxDataGrid.defaultProps = {
    mode: 'full',
}
