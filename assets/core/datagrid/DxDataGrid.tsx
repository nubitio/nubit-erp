import {Field} from "../field/Field";
import {httpClient} from "../http/HttpClient";
import {
    Column,
    ColumnChooser,
    Export,
    FilterRow,
    Item,
    LoadPanel,
    Pager,
    Paging,
    Scrolling,
    Selection,
    Sorting,
    Toolbar
} from "devextreme-react/data-grid";
import {Button, DataGrid} from "devextreme-react";
import CustomStore from "devextreme/data/custom_store";
import {productService} from "../../services/product.service";
import {useCallback, useRef} from "react";
import GridData from "../../components/common/GridData";
import {lastValueFrom} from "rxjs";
import ApiResponse from "../http/ApiResponse";
import {bus} from "../event/EventBus";

export const DxDataGrid = (props: any) => {
    const gridRef = useRef<DataGrid>(null);
    const exportFormats = ['xlsx', 'pdf'];
    const options = props.options;
    const columns = options.fields.map((field: Field, index) => {
        return <Column
            key={index}
            dataField={field.name}
            caption={field.label}
            //alignment={field.align}
            //allowSorting={field.sortable}
            //cellRender={field.formatter}
            visible={field.visible}
        />
    });

    const getIdField = () => {
        return options.fields.find((field: Field) => {
            return field.isIdentity;
        })?.name;
    }

    const fetchData = (loadOptions: any): Promise<GridData<any>> => {
        let subscription = httpClient.get<any[]>(options.url, {params: loadOptions});

        return lastValueFrom(subscription)
            .then((response: ApiResponse<any[]>) => {
                return {
                    data: response.data,
                    totalCount: Number(response.headers['x-total-count'])
                } as GridData<any>;
            });
    }

    const data = new CustomStore({
        key: getIdField(),
        load: (loadOptions) => fetchData(loadOptions),
        insert: (values) => productService.create(values)
    });

    const refresh = useCallback(() => {
        gridRef.current?.instance.refresh();
    }, []);

    const onAddClick = useCallback(() => {
        bus.emit(options.events.ADD, {route: options.routes.ADD});
    }, []);

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
                        showCheckBoxesMode='always'
                        mode='multiple'
                    />
                    <Sorting mode='multiple'/>
                    <Scrolling mode='virtual'/>
                    <Toolbar>
                        <Item location='before'>
                            <div className='grid-header'>{options.title}</div>
                        </Item>
                        <Item location='after' locateInMenu='auto'>
                            <Button
                                icon='plus'
                                text='Add'
                                type='default'
                                stylingMode='contained'
                                onClick={onAddClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto'>
                            <Button
                                icon='edit'
                                text='Edit'
                                type='default'
                                stylingMode='contained'
                                onClick={onAddClick}
                            />
                        </Item>
                        <Item location='after' locateInMenu='auto'>
                            <Button
                                icon='trash'
                                text='Delete'
                                type='danger'
                                stylingMode='contained'
                                onClick={onAddClick}
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
