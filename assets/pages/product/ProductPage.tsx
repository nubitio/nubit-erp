import {useCallback, useEffect, useRef, useState} from "react";
import {Button, DataGrid, DropDownButton} from "devextreme-react";
import {
    Column,
    ColumnChooser,
    Export,
    FilterRow,
    HeaderFilter,
    Item,
    LoadPanel,
    Pager,
    Paging,
    Scrolling,
    Selection,
    Sorting,
    Toolbar
} from "devextreme-react/data-grid";
import {SharedUtils} from "../../core/utils/SharedUtils";
import CustomStore from "devextreme/data/custom_store";
import {productService} from "../../services/product.service";
import {SelectionChangedEvent} from "devextreme/ui/drop_down_button";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import './product.scss'

type FilterContactStatus = '' | 'All';

export const ProductPage = () => {
    const container = "wrapper"
    const gridRef = useRef<DataGrid>(null);
    const exportFormats = ['xlsx', 'pdf'];
    const dropDownOptions = {width: 'auto'};
    const filterStatusList = ['All', 'Active', 'Inactive'];
    const [status, setStatus] = useState(filterStatusList[0]);

    const products = new CustomStore({
        key: "producto_id",
        load: (loadOptions) => productService.getAll(loadOptions),
        insert: (values) => productService.create(values)
    });

    useEffect(() => {
    }, [])

    const filterByStatus = useCallback((e: SelectionChangedEvent) => {
        const {item: status}: { item: FilterContactStatus } = e;
        if (status === 'All') {
            gridRef.current?.instance.clearFilter();
        } else {
            gridRef.current?.instance.filter(['status', '=', status]);
        }

        setStatus(status);
    }, []);

    const refresh = useCallback(() => {
        gridRef.current?.instance.refresh();
    }, []);

    const onAddContactClick = useCallback(() => {
        alert('Add Contact');
    }, []);

    const categoryCellRender = (cell: ColumnCellTemplateData) => {
        return (String(cell.data.categoria.descripcion));
    }

    const categoryHeaderFilter = [
        {text: 'Jaja', value: 'xd'},
        {text: 'Jaja1', value: 'xd1'}
    ]

    return (
        <div id={container} className='view crm-contact-list'>
            <div className='view-wrapper view-wrapper-contact-list'>
                <DataGrid
                    className='grid'
                    noDataText=''
                    focusedRowEnabled
                    height='100%'
                    dataSource={products}
                    allowColumnReordering
                    remoteOperations={true}
                    ref={gridRef}
                >
                    <LoadPanel showPane={false}/>
                    <ColumnChooser enabled/>
                    <Export enabled allowExportSelectedData formats={exportFormats}/>
                    <Selection
                        selectAllMode='allPages'
                        showCheckBoxesMode='always'
                        mode='multiple'
                    />
                    <HeaderFilter visible/>
                    <Sorting mode='multiple'/>
                    <Scrolling mode='virtual'/>
                    <Toolbar>
                        <Item location='before'>
                            <div className='grid-header'>Products</div>
                        </Item>
                        <Item location='before' locateInMenu='auto'>
                            <DropDownButton
                                dataSource={filterStatusList}
                                stylingMode='text'
                                selectedItemKey={status}
                                dropDownOptions={dropDownOptions}
                                useSelectMode
                                onSelectionChanged={filterByStatus}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto'>
                            <Button
                                icon='plus'
                                text='Add Product'
                                type='default'
                                stylingMode='contained'
                                onClick={onAddContactClick}
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
                        <Item name='searchPanel' locateInMenu='auto'/>
                    </Toolbar>
                    <FilterRow visible={true}/>
                    <Column dataField="codigo"/>
                    <Column dataField="tipo_id"/>
                    <Column dataField="descripcion" caption='Nombre' sortOrder='asc'/>
                    <Column
                        dataField="categoria"
                        cellRender={categoryCellRender}
                    >
                        <HeaderFilter dataSource={categoryHeaderFilter} allowSearch={true}/>
                    </Column>
                    <Column dataField="precio" dataType="number" format="#.00"/>
                    <Column
                        dataField="imagen"
                        allowFiltering={false}
                        allowSorting={false}
                        cellRender={SharedUtils.dxImageFormatter}
                        alignment="center"
                    />
                    <Column dataField="estado" cellRender={SharedUtils.dxBooleanFormatter} dataType="boolean"/>
                    <Paging defaultPageSize={20}/>
                    <Pager visible={true}/>
                </DataGrid>
            </div>
        </div>
    );
};
