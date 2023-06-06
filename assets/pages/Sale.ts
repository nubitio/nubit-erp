import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";
import {StorageUtils} from "../core/utils/StorageUtils";
import Customer from "./Customer";
import {Drawer} from "../core/drawer/Drawer";
import {bus} from "../core/event/EventBus";
import {messenger} from "../core/utils/Messenger";
import {SaleService} from "../services/sale.service";
import {DiscountDialog, DiscountDialogEvent} from "../components/discount-dialog/DiscountDialog";
import React from "react";
import {reactRoot} from "../core/common/ReactRoot";
import NumberUtils from "../core/utils/NumberUtils";
import {httpClient} from "../core/http/HttpClient";
import {PriceListItemDto} from "../dto/PriceListItemDto";
import FilterRule from "../core/common/FilterRule";
import {Setting, settings} from "../Settings";
import ApiResponse from "../core/http/ApiResponse";
import {productService} from "../services/product.service";
import {cashboxService} from "../services/cashbox.service";

export default class Sale {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private saleService = new SaleService();
    private priceList: Array<PriceListItemDto> = [];

    static readonly API_URL = Constants.API_BASE_URL + 'sales';

    static readonly EVENTS = {
        ADD: 'sale:add',
        EDIT: 'sale:edit',
        DELETE: 'sale:delete',
        SAVE: 'sale:save',
        CANCEL: 'sale:cancel',
        SUCCESS: 'sale:success',
        LOADING: 'sale:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
        this.checkCashBox();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('movimiento_id')
            .label('Movimiento id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('fecha')
            .label('Fecha')
            .defaultValue('Today')
            .required(true)
            .formatter(SharedUtils.dateFormatter)
            .build());
        let session = StorageUtils.getItem('session');
        let warehouseUrl = `${Constants.API_BASE_URL}branches/${session['branch_id']}/warehouses`;
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('almacen')
            .label('Almacen')
            .url(warehouseUrl)
            .valueField('almacen_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .defaultValue(session['warehouse_id'])
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('cliente')
            .label('Cliente')
            .width(100)
            .url(Constants.API_BASE_URL + 'customers')
            .mode('remote')
            .valueField('ruc')
            .textField('razon_social')
            .defaultValue('00000000')
            .required(true)
            .formatter((value: any) => value['razon_social'])
            .onSelect((record: any) => {
                if (record['price_list']) {
                    this.form.getField('tarifa').combobox('setValue', record['price_list']['id']);
                }
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('tarifa')
            .label('Tarifa')
            .url(Constants.API_BASE_URL + 'price-lists')
            .valueField('id')
            .textField('name')
            .visible(false)
            .onSelect((record: any) => {
                bus.emit(Sale.EVENTS.LOADING, true);
                httpClient.get<PriceListItemDto[]>(`price-list/${record.id}/get`)
                    .subscribe((response) => {
                        this.priceList = response.data;
                        this.updatePricesByPriceList();
                    })
                    .add(() => bus.emit(Sale.EVENTS.LOADING, false));
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('tipo_pago')
            .label('Método de pago')
            .url(Constants.API_BASE_URL + 'payment-methods')
            .valueField('id')
            .textField('name')
            .valueType('number')
            .defaultValue('1')
            .required(true)
            .formatter((value: any, row: any) => value.name)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('numero')
            .label('Voucher')
            .formatter(SharedUtils.voucherFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('invoice')
            .label('Comprobante')
            .formatter(SharedUtils.invoiceFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('total')
            .label('Total')
            .align('right')
            .formatter((value: any) => NumberUtils.formatCurrency(value))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXTAREA)
            .name('glosa')
            .label('Nota')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .col(6)
            .name('descuento')
            .label('Descuento %')
            .defaultValue(0)
            .readonly(true)
            .onClick((e: any) => {
                let total = this.getTotal();
                let discountPercent = this.form.getField('descuento').numberbox('getValue');
                discountPercent = discountPercent ? Number(discountPercent / 100) : 0;
                bus.emit(DiscountDialogEvent.OPEN, {total, discountPercent});
            })
            .onChange(this.calculateGrandTotal.bind(this))
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .col(6)
            .name('total')
            .label('Total')
            .visible(false)
            .disabled(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('estado')
            .label('Anulado')
            .formatter(SharedUtils.invoiceStatusFormatter)
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('created_by')
            .defaultValue(session['user_id'])
            .visible(false)
            .hidden(true)
            .build());

        this.detailFields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('producto')
            .label('Producto')
            .width(100)
            .url(Constants.API_BASE_URL + 'products')
            .mode('remote')
            .valueField('producto_id')
            .textField('descripcion')
            .onSelect((record: any, field: any) => this.onSelectProduct(record, field))
            .formatter((value: any) => value?.descripcion)
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('cantidad')
            .label('Cantidad')
            .align('center')
            .onChange((newValue: number, oldValue: number, field: any) => this.onQuantityChange(newValue, oldValue, field))
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('precio_unitario')
            .label('Precio')
            .align('right')
            .onChange((newValue: number, oldValue: number, field: Field) => this.onPriceChange(newValue, oldValue, field))
            .formatter((value: any) => NumberUtils.formatCurrency(value))
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('precio_total')
            .label('Total')
            .align('right')
            .readonly(true)
            .onChange(this.calculateGrandTotal.bind(this))
            .formatter((value: any) => NumberUtils.formatCurrency(value))
            .required(true)
            .build());
    };

    private checkCashBox() {
        cashboxService.getLast().subscribe((response) => {
            if (response.data.header.length === 0) {
                messenger.confirm(
                    'Confirmar',
                    'No se ha aperturado caja, ¿Desea aperturar caja ahora?',
                    (result) => {
                        if (result) {
                            window.location.href = 'cashbox';
                        } else {
                            window.location.href = '/';
                        }
                    });
            }
        });
    }

    render() {
        this.renderDataGrid();
        this.renderDialog();
        reactRoot.render(
            React.createElement(DiscountDialog)
        );
        this.setupObservers();
    }

    private renderDataGrid() {
        let ref = this;
        let toolbarButtons = [
            {
                text: Constants.TEXT_ADD,
                iconCls: Constants.ICON_ADD,
                handler: function () {
                    bus.emit(Sale.EVENTS.ADD);
                }
            }, {
                text: 'Vista Previa',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    ref.printPreview()
                }
            }, {
                text: 'Imprimir',
                iconCls: 'fas fa-print fa-1x text-primary',
                handler: function () {
                    ref.print();
                }
            }, {
                text: 'Anular',
                iconCls: Constants.ICON_DELETE,
                handler: function () {
                    ref.voidSale();
                }
            }, {
                text: 'Generar Comprobante',
                iconCls: Constants.ICON_INVOICE,
                handler: function () {
                    let row = ref.dataGrid.selectedRow;
                    if (!row) {
                        messenger.show('Información', 'Seleccione una venta para generar el comprobante');
                        return;
                    }
                    if (row['invoice']) {
                        messenger.show('Información', 'El comprobante ya fue generado');
                        return;
                    }
                    if (row['estado'] == 2) {
                        messenger.show('Información', 'La venta se encuentra anulada');
                        return;
                    }
                    let confirmMessage = '';
                    if (row['cliente']['ruc'].length === 11) {
                        confirmMessage = 'Se emitira una factura electrónica, ¿Desea continuar?'
                    } else {
                        confirmMessage = 'Se emitira una boleta electrónica, ¿Desea continuar?'
                    }
                    messenger.confirm('Confirmar', confirmMessage, function (r: boolean) {
                        if (r) {
                            ref.makeInvoice(row['movimiento_id'])
                        }
                    })
                }
            }
        ];

        let session = StorageUtils.getItem('session');
        let branchId = session['branch_id'];
        let branchFilter: FilterRule = {
            field: 'a.local',
            op: 'equal',
            value: branchId
        };
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Ventas',
            url: Sale.API_URL,
            defaultFilters: [branchFilter],
            method: 'get',
            events: Sale.EVENTS,
            toolbar: toolbarButtons,
            detailUrl: Constants.API_BASE_URL + 'sales/{id}/details',
            detailFields: this.detailFields
        });
    }

    private renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 900,
            maxHeight: 700,
            events: Sale.EVENTS
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            url: Sale.API_URL,
            events: Sale.EVENTS,
            detailFields: this.detailFields,
        });

        let drawer = new Drawer({events: Customer.EVENTS});
        let customer = new Customer(drawer.id ?? '');
        customer.renderDialog({width: '100%', inline: true});
        let customerField = this.form.getField('cliente').combobox({
            icons: [{
                iconCls: 'fas fa-plus fa-1x',
                handler: () => {
                    bus.emit(Customer.EVENTS.ADD);
                    drawer.show();
                }
            }]
        });

        bus.on<string>(Customer.EVENTS.SUCCESS, (customerId: string) => {
                customerField.combobox('reload');
                customerField.combobox('setValue', customerId || '');
                drawer.hide();
            }
        );

        bus.on(Form.EVENTS.ROW_REMOVED, () => this.calculateGrandTotal());

        bus.on<number>(DiscountDialogEvent.APPLY, (discountPercent: number) => {
                let discountField = this.form.getField('descuento');
                discountField.numberbox('setValue', Number(discountPercent * 100).toFixed(2));
            }
        )

        bus.on(Sale.EVENTS.ADD, () => this.priceList = []);
    }

    private setupObservers() {
        settings.get(Setting.AUTO_INVOICE).then((setting) => {
            if (setting?.value === 'true') {
                bus.on<ApiResponse<any>>(Sale.EVENTS.SUCCESS, (response) => {
                    this.makeInvoice(response.data['movimiento_id'])
                });
            }
        });
    }

    private onSelectProduct(record: any, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        let price = record.precio;
        if (this.priceList) {
            let priceItem = this.priceList.find((item) => item.id === record.producto_id);
            if (priceItem) {
                price = priceItem.price;
            }
        }
        row['cantidad-' + index].numberbox('setValue', 0);
        row['cantidad-' + index].numberbox('setValue', 1);
        row['precio_unitario-' + index].numberbox('setValue', price);
        row['precio_total-' + index].numberbox('setValue', price);
    }

    private onQuantityChange(newValue: number | string, oldValue: number, field: Field) {
        if (newValue === '') {
            return;
        }

        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        let price = row['precio_unitario-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(Number(newValue) * price).toFixed(2));

        if (newValue > 0) {
            this.checkStock(field);
        }
    }

    private onPriceChange(newValue: number, oldValue: number, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        let quantity = row['cantidad-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * quantity).toFixed(2));
    }

    private getTotal(): number {
        let total = 0;
        let formFields = this.form.getDetailFormFields();
        for (let key in formFields) {
            if (key.startsWith('precio_total-')) {
                total += Number(formFields[key].numberbox('getValue'));
            }
        }
        return total;
    }

    private calculateGrandTotal() {
        let total = this.getTotal();
        let discountPercent = Number(this.form.getField('descuento').numberbox('getValue'));
        let discount = total * (discountPercent / 100);
        total -= discount;
        this.form.getField('total').numberbox('setValue', total);
    }

    private printPreview() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Debe seleccionar una venta');
            return
        }
        let url = 'sale/print/' + selected['movimiento_id'];
        let previewDialog = new Dialog(this.container, {
            width: 350,
            height: 500,
            title: 'Vista Previa',
            modal: true,
            href: url + '?preview=true',
            positiveText: 'Ver PDF',
            preventDefault: true,
        });
        previewDialog.show()

        previewDialog.onPositiveClick(() => {
            window.open(url, '_blank');
            previewDialog.hide();
        });
    }

    private print(saleId?: string) {
        if (saleId) {
            jsWebClientPrint.print(`useDefaultPrinter=checked&saleId=${saleId}`);
            return;
        }

        const selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Debe seleccionar una venta');
            return;
        }

        const id = selected.movimiento_id;
        if (!id) {
            messenger.show('Información', 'ID de venta no válido');
            return;
        }

        jsWebClientPrint.print(`useDefaultPrinter=checked&saleId=${id}`);
    }

    private voidSale() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione una venta');
            return;
        }
        if (selected['estado'] == 2) {
            messenger.show('Información', 'La venta ya se encuentra anulada');
            return;
        }
        messenger.prompt('Confirmar', 'Ingrese un motivo', (reason: string) => {
            let id = selected['movimiento_id'];
            if (reason) {
                this.saleService.voidSale(id, reason).subscribe((data) => {
                    messenger.show('Información', 'Venta anulada');
                    this.dataGrid.reload();
                });
            }
        });
    }

    private makeInvoice(saleId: string) {
        this.dataGrid.showLoading('Generando comprobante...')
        this.saleService.makeInvoice(saleId).subscribe((response) => {
            messenger.show('Información', 'Comprobante generado');
            this.dataGrid.reload();
            this.print(saleId);
        }).add(() => {
            this.dataGrid.hideLoading();
        });
    }

    private updatePricesByPriceList() {
        let formFields = this.form.getDetailFormFields();
        for (let key in formFields) {
            if (key.startsWith('producto-')) {
                let index = key.split('-')[1];
                let row = this.form.getRowFormFieldsByIndex(Number(index));
                let actualPrice = row['precio_unitario-' + index].numberbox('getValue');
                if (this.priceList) {
                    let priceItem = this.priceList.find((item) => item.id === formFields[key].combobox('getValue'));
                    if (priceItem && priceItem.price != actualPrice) {
                        row['precio_unitario-' + index].numberbox('setValue', priceItem.price);
                    }
                }
            }
        }
    }

    private checkStock(field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        settings.get(Setting.CHECK_STOCK).then((setting) => {
            if (setting?.value !== 'true') {
                return;
            }

            this.dialog.setLoading(true, 'Verificando stock...')
            let productId = row['producto-' + index].combobox('getValue');
            let session = JSON.parse(localStorage.getItem('session') ?? '{}');
            let warehouseId = session['warehouse_id'];
            productService.getStock(productId, warehouseId).subscribe((response) => {
                let stock = response.data.stock;
                let quantity = row['cantidad-' + index].numberbox('getValue');
                messenger.show('Información', `Stock disponible: ${stock}`);
                if (quantity > stock) {
                    row['cantidad-' + index].numberbox('clear');
                    row['precio_total-' + index].numberbox('setValue', 0);
                }
            }).add(() => {
                this.dialog.setLoading(false);
            });
        });
    }
}
