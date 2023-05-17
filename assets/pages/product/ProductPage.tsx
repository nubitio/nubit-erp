import {useCallback, useEffect, useRef, useState} from "react";
import {Button, DataGrid, DropDownButton} from "devextreme-react";
import {Column, ColumnChooser, Selection, Export, FilterRow, HeaderFilter, Item, LoadPanel, Pager, Paging, Popup, Scrolling, SearchPanel, Sorting, Toolbar} from "devextreme-react/data-grid";
import {SharedUtils} from "../../core/utils/SharedUtils";
import CustomStore from "devextreme/data/custom_store";
import { productService } from "../../services/product.service";
import { SelectionChangedEvent } from "devextreme/ui/drop_down_button";
import { ColumnCellTemplateData } from "devextreme/ui/data_grid";

type FilterContactStatus = '' | 'All';

export const ProductPage = () => {
    const container = "wrapper"
    const gridRef = useRef<DataGrid>(null);
    const exportFormats = ['xlsx', 'pdf'];
    const dropDownOptions = { width: 'auto' };
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
        const { item: status }: { item: FilterContactStatus } = e;
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
        console.log(cell);
        return (String(cell.data.categoria.descripcion));
    }

    return (
        <div id={container}>
            <DataGrid
                ref={gridRef}
                height='100%'
                dataSource={products}
                showBorders={true}
                columnMinWidth={50}
                columnAutoWidth={true}
                repaintChangesOnly={true}
                remoteOperations={true}
            >
                <LoadPanel showPane={false} />
                <SearchPanel visible placeholder='Contact Search' />
                <ColumnChooser enabled />
                <Export enabled allowExportSelectedData formats={exportFormats} />
                <Selection
                    selectAllMode='allPages'
                    showCheckBoxesMode='always'
                    mode='multiple'
                />
                <HeaderFilter visible />
                <Sorting mode='multiple' />
                <Scrolling mode='virtual' />
                <Toolbar>
                    <Item location='before'>
                        <div className='grid-header'>Contacts</div>
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
                        text='Add Contact'
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
                    <div className='separator' />
                    </Item>
                    <Item name='exportButton' />
                    <Item location='after' locateInMenu='auto'>
                    <div className='separator' />
                    </Item>
                    <Item name='columnChooserButton' locateInMenu='auto' />
                    <Item name='searchPanel' locateInMenu='auto' />
                </Toolbar>
                <FilterRow visible={true}/>
                <Column dataField="codigo"/>
                <Column dataField="tipo"/>
                <Column dataField="descripcion"/>
                <Column dataField="categoria" cellRender={categoryCellRender}/>
                <Column dataField="precio" dataType="number" format="#.00"/>
                <Column dataField="imagen" cellRender={SharedUtils.dxImageFormatter} alignment="center"/>
                <Column dataField="estado" cellRender={SharedUtils.dxBooleanFormatter} dataType="boolean"/>
                <Paging defaultPageSize={10}/>
                <Pager visible={true}/>
            </DataGrid>
        </div>
    );
};