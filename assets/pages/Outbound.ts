import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {StorageUtils} from "../core/utils/StorageUtils";
import {ProductService} from "../components/product/product.service";

export default class Outbound {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private productService = new ProductService();

    static readonly ROUTES = {
        ADD: 'outbound/add',
    }

    static readonly EVENTS = {
        ADD: 'outbound:add',
        EDIT: 'outbound:edit',
        DELETE: 'outbound:delete',
        SAVE: 'outbound:save',
        CANCEL: 'outbound:cancel',
        SUCCESS: 'outbound:success',
        LOADING: 'outbound:loading',
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
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .defaultValue('Today')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('operacion_id')
            .label('Operacion')
            .url('api/operations?filterRules=[{"field":"tipo_id","op":"equal","value":"2"}]')
            .valueField('operacion_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('operacion')
            .label('Operacion')
            .width(100)
            .build());
        let session = StorageUtils.getItem('session');
        let warehouseUrl = `api/branches/${session['branch_id']}/warehouses`;
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
            .type(FormType.NONE)
            .name('almacen')
            .label('Almacen')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('glosa')
            .label('Glosa')
            .visible(false)
            .build());

        this.detailFields.push(new FieldBuilder()
            .type(FormType.SELECT)
            .valueField('producto_id')
            .textField('descripcion')
            .name('producto_id')
            .label('Producto')
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('cantidad')
            .label('Cantidad')
            .required(true)
            .build());
    };

    render() {
        this.productService.getProducts('1').subscribe((response) => {
            this.detailFields.forEach((field) => {
                if (field.name === 'producto_id') {
                    field.data = response.data;
                }
            });
            this.renderDataGrid();
            this.renderDialog();
        });
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Outbound',
            url: 'outbound/get',
            routes: Outbound.ROUTES,
            events: Outbound.EVENTS,
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
                    }
                ],
            }
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 750,
            height: 450,
            events: Outbound.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Outbound.ROUTES,
            events: Outbound.EVENTS,
            detailFields: this.detailFields,
        });
    }
}