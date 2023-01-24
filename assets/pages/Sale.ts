import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";
import {StorageUtils} from "../core/utils/StorageUtils";
import {ProductDto} from "../components/product/product.dto";
import {ProductService} from "../components/product/product.service";
import Customer from "./Customer";
import {Drawer} from "../core/drawer/Drawer";
import {Event} from "../core/event/Event";
import {bus} from "../core/event/EventBus";
import {messenger} from "../core/utils/Messenger";
import {SaleService} from "../services/sale.service";

export default class Sale {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private productService = new ProductService();
    private saleService = new SaleService();

    static readonly API_URL = 'api/sales';

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
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('movimiento_id')
            .label('Movimiento id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .defaultValue('Today')
            .required(true)
            .formatter(SharedUtils.dateFormatter)
            .build());
        let session = StorageUtils.getItem('session');
        let localId = session['local_id'];
        let warehouseUrl = `api/local/${localId}/warehouse`;
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('almacen_id')
            .label('Almacen')
            .url(warehouseUrl)
            .valueField('almacen_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('cliente')
            .label('Cliente')
            .width(100)
            .url('api/customer')
            .valueField('ruc')
            .textField('razon_social')
            .defaultValue('00000000')
            .required(true)
            .formatter((value: any, row: any) => value['razon_social'])
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('tipo_pago')
            .label('Metodo de pago')
            .url('api/payment-method')
            .valueField('id')
            .textField('name')
            .defaultValue('1')
            .required(true)
            .formatter((value: any, row: any) => value.name)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('numero')
            .label('Voucher')
            .formatter(SharedUtils.voucherFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('comprobante')
            .label('Comprobante')
            .formatter(SharedUtils.invoiceFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('total')
            .label('Total')
            .align('right')
            .formatter((value: any, row: any) => {
                if (row['comprobante']) {
                    return Intl.NumberFormat('es-PE', {
                        style: 'currency',
                        currency: 'PEN'
                    }).format(row['comprobante']['total']);
                } else {
                    let total = row['details'].reduce((total: number, detail: any) => {
                        return total + detail['precio_total'];
                    }, 0);
                    return Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'}).format(total);
                }
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('glosa')
            .label('Nota')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .col(6)
            .name('descuento')
            .label('Descuento %')
            .defaultValue(0)
            .onChange(this.calculateGrandTotal.bind(this))
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .col(6)
            .name('total')
            .label('Total')
            .visible(false)
            .disabled(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('estado')
            .label('Estado')
            .formatter(SharedUtils.invoiceStatusFormatter)
            .align('center')
            .build());

        this.detailFields.push(new FieldBuilder()
            .type(FormType.SELECT)
            .name('producto_id')
            .label('Producto')
            .valueField('producto_id')
            .textField('descripcion')
            .onSelect((record: any, field: any) => this.onSelectProduct(record, field))
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('cantidad')
            .label('Cantidad')
            .onChange((newValue: number, oldValue: number, field: any) => this.onQuantityChange(newValue, oldValue, field))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_unitario')
            .label('Precio')
            .onChange((newValue: number, oldValue: number, field: Field) => this.onPriceChange(newValue, oldValue, field))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_total')
            .label('Total')
            .readonly(true)
            .onChange(this.calculateGrandTotal.bind(this))
            .build());
    };

    render() {
        this.productService.getProducts('1').subscribe((data: ProductDto[]) => {
            this.detailFields.forEach((field) => {
                if (field.name === 'producto_id') {
                    field.data = data;
                }
            });
            this.renderDataGrid();
            this.renderDialog();
        });
    }

    private renderDataGrid() {
        let ref = this;
        let toolbarButtons = [
            {
                text: Constants.TEXT_ADD,
                iconCls: Constants.ICON_ADD,
                handler: function () {
                    bus.emit(new Event(Sale.EVENTS.ADD));
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
                text: 'Comprobante',
                iconCls: Constants.ICON_INVOICE,
                handler: function () {
                    ref.makeInvoice();
                }
            }
        ];

        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Ventas',
            url: Sale.API_URL,
            method: 'get',
            events: Sale.EVENTS,
            toolbar: toolbarButtons,
            subGrid: {
                url: 'api/sale/detail/',
                columns: [
                    {
                        field: 'producto',
                        title: 'Producto',
                        width: 100
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        align: 'center'
                    }, {
                        field: 'precio_unitario',
                        title: 'Unitario',
                        align: 'right'
                    }, {
                        field: 'precio_total',
                        title: 'Total',
                        align: 'right'
                    }
                ],
            }
        });
    }

    private renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 900,
            maxHeight: 600,
            events: Sale.EVENTS
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            url: Sale.API_URL,
            events: Sale.EVENTS,
            detailFields: this.detailFields,
        });

        let drawer = new Drawer({events: Customer.EVENTS});
        let customer = new Customer(drawer.id ?? '');
        customer.renderDialog();
        let customerField = this.form.getField('cliente').combobox({
            icons: [{
                iconCls: 'fas fa-plus fa-1x',
                handler: () => {
                    bus.emit(new Event(Customer.EVENTS.ADD));
                    drawer.show();
                }
            }]
        });
        bus.on(
            action => action.name == Customer.EVENTS.SUCCESS,
            (action: Event) => {
                customerField.combobox('reload');
                customerField.combobox('setValue', action.data || '');
                drawer.hide();
            }
        );
        bus.on(
            action => action.name == Form.EVENTS.ROW_REMOVED,
            (action: Event) => {
                this.calculateGrandTotal();
            }
        );
    }

    private onSelectProduct(record: any, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        row['cantidad-' + index].numberbox('setValue', 1);
        row['precio_unitario-' + index].numberbox('setValue', record.precio);
        row['precio_total-' + index].numberbox('setValue', record.precio);
    }

    private onQuantityChange(newValue: number, oldValue: number, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        let price = row['precio_unitario-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * price).toFixed(2));
    }

    private onPriceChange(newValue: number, oldValue: number, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        let quantity = row['cantidad-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * quantity).toFixed(2));
    }

    private calculateGrandTotal() {
        let total = 0;
        let formFields = this.form.getDetailFormFields();
        for (let key in formFields) {
            if (key.startsWith('precio_total-')) {
                total += Number(formFields[key].numberbox('getValue'));
            }
        }
        total -= Number(this.form.getField('descuento').numberbox('getValue'));
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

    private print(sale: any = null) {
        let selected = this.dataGrid.selectedRow ?? sale;
        if (!selected) {
            messenger.show('Información', 'Debe seleccionar una venta');
            return
        }
        jsWebClientPrint.print('useDefaultPrinter=checked&saleId=' + selected['movimiento_id']);
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

    private makeInvoice() {
        let ref = this;
        let row = this.dataGrid.selectedRow;
        if (!row) {
            messenger.show('Información', 'Seleccione una venta para generar el comprobante');
            return;
        }
        if (row['comprobante_id']) {
            messenger.show('Información', 'El comprobante ya fue generado');
            return;
        }
        if (row['estado'] == 2) {
            messenger.show('Información', 'La venta se encuentra anulada');
            return;
        }
        let confirmMessage = '';
        if (row['cliente_id'].length === 11) {
            confirmMessage = 'Se emitira una factura electrónica, ¿Desea continuar?'
        } else {
            confirmMessage = 'Se emitira una boleta electrónica, ¿Desea continuar?'
        }
        messenger.confirm('Confirmar', confirmMessage, function (r: boolean) {
            if (r) {
                ref.saleService.makeInvoice(row['movimiento_id']).subscribe((data) => {
                    messenger.show('Información', 'Comprobante generado');
                    ref.dataGrid.reload();
                });
            }
        })
    }
}
