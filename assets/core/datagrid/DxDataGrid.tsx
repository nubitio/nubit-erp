import {Field} from "../field/Field";
import {
    Column,
    ColumnChooser,
    Export,
    FilterRow,
    Item,
    LoadPanel,
    Lookup,
    Pager,
    Paging,
    Scrolling,
    Selection,
    Sorting,
    Toolbar
} from "devextreme-react/data-grid";
import {Button, DataGrid} from "devextreme-react";
import {useCallback, useRef} from "react";
import {bus} from "../event/EventBus";
import {confirm} from "devextreme/ui/dialog";
import notify from "devextreme/ui/notify";
import {FieldType} from "../field/FieldType";
import {dxUtils} from "../common/DxUtils";

export const DxDataGrid = (props: any) => {
    const gridRef = useRef<DataGrid>(null);
    const exportFormats = ['xlsx', 'pdf'];
    const options = props.options;
    const columns = options.fields.map((field: Field, index) => {
        let lookup;
        if (field.type === FieldType.ENTITY) {
            lookup = <Lookup
                dataSource={{
                    store: dxUtils.getDataStore(field.url, field.valueField),
                    paginate: true,
                    pageSize: 10
                }}
                valueExpr={field.valueField}
                displayExpr={field.textField}
            />
        }
        if (field.type === FieldType.SWITCH) {
            lookup = <Lookup
                dataSource={[{value: true, text: 'Si'}, {value: false, text: 'No'}]}
                valueExpr="value"
                displayExpr="text"
            />
        }
        return <Column
            key={index}
            dataField={field.name}
            caption={field.label}
            alignment={field.align}
            allowSorting={field.sortable}
            cellRender={field.formatter}
            visible={field.visible}
        >
            {lookup}
        </Column>
    });

    const getIdField = () => {
        return options.fields.find((field: Field) => {
            return field.isIdentity;
        })?.name;
    }

    const data = dxUtils.getDataStore(options.url, getIdField());

    const refresh = useCallback(() => {
        gridRef.current?.instance.refresh();
    }, []);

    const onAddClick = useCallback(() => {
        bus.emit(options.events.ADD);
    }, [options.events.ADD]);

    const onEditClick = useCallback(() => {
        let selectedRowsData = gridRef.current?.instance.getSelectedRowsData();
        console.log(selectedRowsData)
        if (selectedRowsData && selectedRowsData.length > 0) {
            bus.emit(options.events.EDIT, {row: selectedRowsData[0]});
        } else {
            notify('Debe seleccionar un registro', 'error', 2000);
        }
    }, [options.events.EDIT]);

    const onDeleteClick = useCallback(() => {
        let selectedRowsData = gridRef.current?.instance.getSelectedRowsData();
        if (selectedRowsData && selectedRowsData.length > 0) {
            let result = confirm('¿Está seguro de eliminar el registro?', 'Confirmar eliminación');
            result.then((dialogResult) => {
                if (dialogResult) {
                    if (selectedRowsData) {
                        bus.emit(options.events.DELETE, {row: selectedRowsData[0]});
                    }
                }
            });
        } else {
            notify('Debe seleccionar un registro', 'error', 2000);
        }
    }, [options.events.DELETE]);

    return (
        <div className='view crm-contact-list'>
            <div className='view-wrapper view-wrapper-contact-list'>
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
                        <Item location='after' locateInMenu='auto' visible={options.allowAdd}>
                            <Button
                                icon='plus'
                                text='Nuevo'
                                type='success'
                                stylingMode='outlined'
                                onClick={onAddClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowEdit}>
                            <Button
                                icon='edit'
                                text='Editar'
                                type='default'
                                stylingMode='outlined'
                                onClick={onEditClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto' visible={options.allowDelete}>
                            <Button
                                icon='trash'
                                text='Eliminar'
                                type='danger'
                                stylingMode='outlined'
                                onClick={onDeleteClick}
                            />
                        </Item>
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
                    {columns}
                    <Paging defaultPageSize={20}/>
                    <Pager visible={true}/>
                </DataGrid>
            </div>
        </div>
    );
}
