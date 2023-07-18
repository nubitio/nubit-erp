import "./PriceListPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {PriceListFields} from "./PriceListFields";
import {useEffect, useRef} from "react";
import notify from "devextreme/ui/notify";
import {DataGrid} from "devextreme-react";
import {Column, Editing, Scrolling} from "devextreme-react/data-grid";
import {bus} from "../../core/event/EventBus";
import {httpClient} from "../../core/http/HttpClient";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import NumberUtils from "../../core/utils/NumberUtils";

export const PriceListPage = () => {
    const container = "wrapper";
    const fields = PriceListFields();
    const gridRef = useRef<DxDataGridEvents>(null);
    const pricesRef = useRef<DataGrid>(null);

    const API_URL = Constants.API_BASE_URL + 'price-lists';

    const EVENTS = {
        ADD: 'price-list:add',
        EDIT: 'price-list:edit',
        DELETE: 'price-list:delete',
        SAVE: 'price-list:save',
        CANCEL: 'price-list:cancel',
        SUCCESS: 'price-list:success',
        LOADING: 'price-list:loading',
    }

    const PRICES_EVENT = {
        ADD: 'price:add',
        EDIT: 'price:edit',
        DELETE: 'price:delete',
        SAVE: 'price:save',
        CANCEL: 'price:cancel',
        SUCCESS: 'price:success',
        LOADING: 'price:loading',
    }

    const toolbar = [
        {
            icon: 'fas fa-tags fa-1x',
            text: 'Precios',
            type: 'info',
            onClick: () => {
                let selectedRow = gridRef.current?.getSelectedRow();
                if (!selectedRow) {
                    notify({
                        message: 'Debe seleccionar una tarifa',
                        type: 'warning',
                        displayTime: 3000,
                    }, {position: 'top right'});
                    return;
                }
                bus.emit(PRICES_EVENT.ADD, {title: 'Precios de ' + selectedRow.name});
            }
        }
    ]

    let alreadySetup = false;

    const setupObservers = () => {
        bus.on(PRICES_EVENT.ADD, () => {
            let selectedRow = gridRef.current?.getSelectedRow();
            httpClient.get<any>('price-list/' + selectedRow.id + '/get')
                .subscribe(response => {
                    pricesRef.current?.instance.beginCustomLoading('Cargando precios...');
                    pricesRef.current?.instance.option('dataSource', response.data);
                    pricesRef.current?.instance.endCustomLoading();
                })
        })

        bus.on(PRICES_EVENT.SAVE, () => {
            let selectedRow = gridRef.current?.getSelectedRow();
            let pricesData: any[] = [];
            let updatedPrices = pricesRef.current?.instance.getDataSource().items();
            updatedPrices?.forEach((price: any) => {
                if (!price.updated) return;
                pricesData.push({
                    price_list_id: selectedRow.id,
                    product_id: price.id,
                    price: price.price,
                })
            })
            httpClient.post<any>('price-list/update', pricesData)
                .subscribe(response => {
                    notify({
                        message: response.data.msg,
                        type: 'success',
                        displayTime: 3000,
                    }, {position: 'top right'});
                    bus.emit(PRICES_EVENT.SUCCESS);
                })
        })
    }

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, [])

    return (
        <div id={container}>
            <DxDataGrid
                ref={gridRef}
                title={'Tarifas'}
                toolbar={toolbar}
                url={API_URL}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                events={EVENTS}
            />
            <DxDialog
                events={EVENTS}
            >
                <DxForm
                    url={API_URL}
                    fields={fields}
                    events={EVENTS}
                />
            </DxDialog>
            <DxDialog
                events={PRICES_EVENT}
            >
                <DataGrid
                    ref={pricesRef}
                    dataSource={[]}
                    showBorders={true}
                    showRowLines={true}
                    onRowUpdated={(e: any) => {
                        e.data.updated = true;
                        e.component.saveEditData();
                    }}
                >
                    <Editing mode='cell' allowUpdating={true}/>
                    <Scrolling mode='virtual'/>
                    <Column dataField='id' caption='ID' visible={false}/>
                    <Column dataField='name' caption='Producto' minWidth={300} allowEditing={false}/>
                    <Column dataField='price' caption='Precio' alignment='right' dataType='number'
                            cellRender={(cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value)}/>
                    <Column dataField='updated' caption='' visible={false}/>
                </DataGrid>
            </DxDialog>
        </div>
    );
};
