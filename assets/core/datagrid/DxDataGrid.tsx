import './DxDataGrid.scss';
import {
    ColumnChooser,
    Export,
    FilterRow,
    Item,
    LoadPanel,
    MasterDetail,
    Pager,
    Paging,
    Scrolling,
    Selection,
    Sorting,
    Toolbar
} from "devextreme-react/data-grid";
import {Button, DataGrid} from "devextreme-react";
import {useCallback, useEffect, useRef, useState} from "react";
import {bus} from "../event/EventBus";
import notify from "devextreme/ui/notify";
import {dxUtils} from "../common/DxUtils";
import CustomStore from "devextreme/data/custom_store";
import {buildColumns, buildToolbar, getIdField} from "./DxDataGridUtils";
import {DxDetailDataGrid} from "./DxDetailDataGrid";

export const DxDataGrid = ({options}: any) => {
    const gridRef = useRef<DataGrid>(null);
    const [data, setData] = useState<CustomStore | null>(null);
    const exportFormats = ['xlsx', 'pdf'];

    const refresh = useCallback(() => {
        gridRef.current?.instance.refresh();
    }, []);

    const onAddClick = useCallback(() => {
        bus.emit(options.events.ADD);
    }, [options.events.ADD]);

    const onEditClick = useCallback(() => {
        let selectedRowsData = gridRef.current?.instance.getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            bus.emit(options.events.EDIT, {row: selectedRowsData[0]});
        } else {
            notify('Debe seleccionar un registro', 'error', 2000);
        }
    }, [options.events.EDIT]);

    const onDeleteClick = useCallback(() => {
        let selectedRowsData = gridRef.current?.instance.getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            bus.emit(options.events.DELETE, {row: selectedRowsData[0]});
        } else {
            notify('Debe seleccionar un registro', 'error', 2000);
        }
    }, [options.events.DELETE]);

    const setupObservers = () => {
        bus.on(options.events.SUCCESS, refresh);
    }

    let alreadyLoaded = false;
    let alreadySetup = false;

    useEffect(() => {
        if (!alreadyLoaded) {
            setData(dxUtils.getDataStore(options.url, getIdField(options.fields)));
            alreadyLoaded = true;
        }
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

    const renderDetail = useCallback((e: any) => {
        let detailUrl = options.detailUrl.replace('{id}', e.key);

        return <DxDetailDataGrid options={{
            url: detailUrl,
            fields: options.detailFields,
        }}/>
    }, []);

    return (
        <div className='view datagrid-list'>
            <div className='view-wrapper view-wrapper-datagrid-list'>
                <DataGrid
                    className='grid'
                    noDataText=''
                    focusedRowEnabled
                    height='100%'
                    dataSource={data}
                    allowColumnReordering
                    remoteOperations={true}
                    syncLookupFilterValues={false}
                    ref={gridRef}
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
                    <Toolbar>
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
                    <MasterDetail enabled={options.detailFields?.length > 0} render={renderDetail}/>
                    <Paging defaultPageSize={20}/>
                    <Pager visible={true}/>
                </DataGrid>
            </div>
        </div>
    );
}
