import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {bus} from "../core/event/EventBus";
import {Drawer} from "../core/drawer/Drawer";
import {httpClient} from "../core/http/HttpClient";
import NumberUtils from "../core/utils/NumberUtils";

export default class PriceList {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/price-lists';

    static readonly EVENTS = {
        ADD: 'price-list:add',
        EDIT: 'price-list:edit',
        DELETE: 'price-list:delete',
        SAVE: 'price-list:save',
        CANCEL: 'price-list:cancel',
        SUCCESS: 'price-list:success',
        LOADING: 'price-list:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('name')
            .label('Nombre')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.renderPricesManager();
    }

    renderDataGrid() {
        let toolbar = [
            {
                text: 'Precios',
                iconCls: 'fas fa-tags fa-1x',
                handler: () => {
                    let row = this.dataGrid.selectedRow;
                    if (row) {
                        bus.emit('price-list:rules', row);
                    } else {
                        SharedUtils.showMessage('info', 'Debe seleccionar un registro');
                    }
                }
            }
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Tarifas',
            url: PriceList.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: PriceList.EVENTS,
            toolbar: toolbar,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: PriceList.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: PriceList.API_URL,
            events: PriceList.EVENTS,
        });
    }

    private renderPricesManager() {
        let EVENTS = {
            ADD: 'prices:add',
            EDIT: 'prices:edit',
            DELETE: 'prices:delete',
            SAVE: 'prices:save',
            CANCEL: 'prices:cancel',
            SUCCESS: 'prices:success',
            LOADING: 'prices:loading',
        }

        let drawer = new Drawer({
            width: 400,
            events: EVENTS,
        });

        let pricesDialog = new Dialog(drawer.id ?? '', {
            width: '100%',
            height: window.innerHeight,
            maxHeight: window.innerHeight,
            modal: false,
            inline: true,
            events: EVENTS,
        });

        let container = document.getElementById(<string>pricesDialog.id);

        if (!container) {
            return;
        }

        let pricesContainer = document.createElement('table');
        pricesContainer.setAttribute('id', 'prices');
        container.appendChild(pricesContainer);

        let pricesGrid = $('#prices');

        pricesGrid.datagrid({
            pagination: false,
            columns: [[
                {field: 'name', title: 'Nombre', width: 100},
                {
                    field: 'price',
                    title: 'Precio',
                    align: 'right',
                    formatter: (value: any) => NumberUtils.formatCurrency(value),
                    editor: {type: 'numberbox', options: {precision: 2}}
                },
            ]],
        });

        pricesGrid.datagrid('enableCellEditing');

        bus.on<any>('price-list:rules', (payload) => {
                let row = payload;
                pricesDialog.show('Lista de precios: ' + row.name);
                drawer.show();
                pricesGrid.datagrid('loadData', []);
                bus.emit(EVENTS.LOADING, true);
                httpClient.get<any>('price-list/' + row.id + '/get').subscribe(
                    response => {
                        pricesGrid.datagrid('loadData', response.data);
                        bus.emit(EVENTS.LOADING, false);
                    }
                )
            }
        )

        bus.on(EVENTS.SAVE, () => {
                let row = this.dataGrid.selectedRow;
                let data = pricesGrid.datagrid('getChanges');
                let prices = [];
                for (let i = 0; i < data.length; i++) {
                    prices.push({
                        price_list_id: row.id,
                        product_id: data[i].id,
                        price: parseFloat(data[i].price)
                    });
                }
                console.log(prices);
                httpClient.post<any>('price-list/update', prices).subscribe(
                    response => {
                        SharedUtils.showMessage('Info', response.data.msg);
                        pricesDialog.hide();
                        drawer.hide();
                        bus.emit(EVENTS.SUCCESS, true);
                    }
                )
            }
        )
    }
}