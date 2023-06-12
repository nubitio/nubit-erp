import {useCallback, useEffect, useRef, useState} from "react";
import {Button, DataGrid} from "devextreme-react";
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
import {SharedUtils} from "../../core/utils/SharedUtils";
import CustomStore from "devextreme/data/custom_store";
import {productService} from "../../services/product.service";
import {ColumnCellTemplateData, RowClickEvent} from "devextreme/ui/data_grid";
import './product.scss'
import CategoryService from "../../services/category.service";
import {ContactPanel, FormPopup} from "../../components";
import {ProductForm} from "./ProductForm";
import "./ProductPage.scss";

export const ProductPage = () => {
    const container = "wrapper"
    const gridRef = useRef<DataGrid>(null);
    const exportFormats = ['xlsx', 'pdf'];
    const [isPanelOpened, setPanelOpened] = useState(false);
    const [contactId, setContactId] = useState<string>('');
    const [popupVisible, setPopupVisible] = useState(false);

    const products = new CustomStore({
        key: "producto_id",
        load: (loadOptions) => productService.getAll(loadOptions),
        insert: (values) => productService.create(values)
    });

    useEffect(() => {
    }, [])

    const changePopupVisibility = useCallback(() => {
        setPopupVisible(!popupVisible);
    }, [popupVisible]);

    const changePanelOpened = useCallback(() => {
        setPanelOpened(!isPanelOpened);
        gridRef.current?.instance.option('focusedRowIndex', -1);
    }, [isPanelOpened]);

    const changePanelPinned = useCallback(() => {
        gridRef.current?.instance.updateDimensions();
    }, []);

    const refresh = useCallback(() => {
        gridRef.current?.instance.refresh();
    }, []);

    const onAddContactClick = useCallback(() => {
        setPopupVisible(true);
    }, []);

    const onRowClick = useCallback(({data}: RowClickEvent) => {
        console.log(data)
        setContactId(data.producto_id);
        setPanelOpened(true);
    }, []);

    const typeCellRender = (cell: ColumnCellTemplateData) => {
        return (cell.data.tipo_id === 1 ? 'Producto' : 'Servicio');
    }

    const categoryCellRender = (cell: ColumnCellTemplateData) => {
        return (String(cell.data.categoria.descripcion));
    }

    const productTypeDataSource = {
        store: {
            type: 'array',
            data: [
                {id: 1, name: 'Producto'},
                {id: 2, name: 'Servicio'}
            ],
            key: 'id'
        }
    }

    const categoryDataSource = {
        store: new CustomStore(
            {
                key: "categoria_id",
                load: (loadOptions) => CategoryService.getAll(loadOptions)
            }
        )
    }

    const activeDataSource = {
        store: {
            type: 'array',
            data: [
                {id: 1, name: 'Si'},
                {id: 0, name: 'No'}
            ],
        }
    }

    return (
        <div id={container} className='view crm-contact-list'>
            <div className='view-wrapper view-wrapper-contact-list'>
                <DataGrid
                    className='grid'
                    noDataText=''
                    focusedRowEnabled
                    height='100%'
                    dataSource={products}
                    onRowClick={onRowClick}
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
                        showCheckBoxesMode='always'
                        mode='multiple'
                    />
                    <Sorting mode='multiple'/>
                    <Scrolling mode='virtual'/>
                    <Toolbar>
                        <Item location='before'>
                            <div className='grid-header'>Products</div>
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
                    <Column dataField="tipo_id" caption="Tipo" cellRender={typeCellRender} alignment="left">
                        <Lookup dataSource={productTypeDataSource} valueExpr="id" displayExpr="name"/>
                    </Column>
                    <Column dataField="descripcion" caption='Nombre' sortOrder='asc' minWidth={350}/>
                    <Column
                        dataField="categoria"
                        cellRender={categoryCellRender}
                    >
                        <Lookup dataSource={categoryDataSource} valueExpr="categoria_id" displayExpr="descripcion"/>
                    </Column>
                    <Column dataField="precio" dataType="number" format="#.00"/>
                    <Column
                        dataField="imagen"
                        allowFiltering={false}
                        allowSorting={false}
                        cellRender={SharedUtils.dxImageFormatter}
                        alignment="center"
                    />
                    <Column dataField="estado"
                            cellRender={SharedUtils.dxBooleanFormatter}
                            dataType="boolean"
                            alignment="center"
                    >
                        <Lookup dataSource={activeDataSource} valueExpr="id" displayExpr="name"/>
                    </Column>
                    <Paging defaultPageSize={20}/>
                    <Pager visible={true}/>
                </DataGrid>
                <ContactPanel contactId={contactId} isOpened={isPanelOpened} changePanelOpened={changePanelOpened}
                              changePanelPinned={changePanelPinned}/>
                <FormPopup title='New Contact' visible={popupVisible} setVisible={changePopupVisibility}>
                    <ProductForm/>
                </FormPopup>
            </div>
        </div>
    );
};
