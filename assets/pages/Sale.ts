import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";
import {Constants} from "../Constants";
import {StorageUtils} from "../utils/StorageUtils";
import {ProductDto} from "../dto/product.dto";
import {ProductService} from "../services/product.service";
import Customer from "./Customer";
import {Drawer} from "../Drawer";
import {Action} from "../Action";
import {bus} from "../EventBus";

export default class Sale {
    private container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};
    private productService = new ProductService();

    static readonly ROUTES = {
        ADD: 'sale/add',
        EDIT: 'sale/edit/',
        DELETE: 'sale/delete/',
    }

    static readonly EVENTS = {
        ADD: 'sale:add',
        EDIT: 'sale:edit',
        DELETE: 'sale:delete',
        SAVE: 'sale:save',
        CANCEL: 'sale:cancel',
        SUCCESS: 'sale:success',
        LOADING: 'sale:loading',
    }

    constructor(container, options) {
        this.container = container;
        this.dataGrid = null;
        this.dialog = null;
        this.form = null;
        this.options = options || {};
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.HIDDEN)
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
            .name('cliente_id')
            .label('Cliente')
            .url('api/customer')
            .valueField('ruc')
            .textField('razon_social')
            .defaultValue('00000000')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('cliente')
            .label('Cliente')
            .width(100)
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
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('numero')
            .label('Voucher')
            .formatter(SharedUtils.voucherFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('inumero')
            .label('Comprobante')
            .formatter(SharedUtils.invoiceFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('gravada')
            .label('Total')
            .align('right')
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
            .type(FormType.HIDDEN)
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
            .onSelect((record, field) => this.onSelectProduct(record, field))
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('cantidad')
            .label('Cantidad')
            .onChange((newValue, oldValue, field) => this.onQuantityChange(newValue, oldValue, field))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_unitario')
            .label('Precio')
            .onChange((newValue, oldValue, field) => this.onPriceChange(newValue, oldValue, field))
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
                    bus.emit(new Action(Sale.EVENTS.ADD, {route: Sale.ROUTES.ADD}));
                }
            }, {
                text: 'Vista Previa',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    //venta_preview();
                }
            }, {
                text: 'Imprimir',
                iconCls: 'fas fa-print fa-1x text-primary',
                handler: function () {
                    //venta_print();
                }
            }, {
                text: 'Anular',
                iconCls: Constants.ICON_DELETE,
                handler: function () {
                    //venta_anular();
                }
            }, {
                text: 'Comprobante',
                iconCls: Constants.ICON_INVOICE,
                handler: function () {
                    //makeInvoice();
                }
            }
        ];

        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Ventas',
            url: 'sale/get',
            routes: {},
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
        this.options['width'] = 900;
        this.options['maxHeight'] = 600;
        this.options['events'] = Sale.EVENTS;
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Sale.ROUTES,
            events: Sale.EVENTS,
            detailFields: this.detailFields,
        });

        let drawer = new Drawer({events: Customer.EVENTS});
        let customer = new Customer(drawer.id, {width: '100%', height: '100%', modal: true});
        customer.renderDialog();
        let customerField = this.form.getField('cliente_id').combobox({
            icons: [{
                iconCls: 'fas fa-plus fa-1x',
                handler: function (e) {
                    bus.emit(new Action(Customer.EVENTS.ADD));
                    drawer.show();
                }
            }]
        });
        bus.on(
            action => action.name == Customer.EVENTS.SUCCESS,
            (action: Action) => {
                customerField.combobox('reload');
                customerField.combobox('setValue', action.data || '');
                drawer.hide();
            }
        );
        bus.on(
            action => action.name == Form.EVENTS.ROW_REMOVED,
            (action: Action) => {
                this.calculateGrandTotal();
            }
        );
    }

    private onSelectProduct(record, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
        row['cantidad-' + index].numberbox('setValue', 1);
        row['precio_unitario-' + index].numberbox('setValue', record.precio);
        row['precio_total-' + index].numberbox('setValue', record.precio);
    }

    private onQuantityChange(newValue, oldValue, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
        let price = row['precio_unitario-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * price).toFixed(2));
    }

    private onPriceChange(newValue, oldValue, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
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
}
