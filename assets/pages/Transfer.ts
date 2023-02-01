import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {ProductService} from "../components/product/product.service";
import {StorageUtils} from "../core/utils/StorageUtils";

export default class Transfer {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private productService = new ProductService();

    static readonly ROUTES = {
        ADD: 'transfer/add',
        EDIT: 'transfer/edit/',
        DELETE: 'transfer/delete/',
    }

    static readonly EVENTS = {
        ADD: 'transfer:add',
        EDIT: 'transfer:edit',
        DELETE: 'transfer:delete',
        SAVE: 'transfer:save',
        CANCEL: 'transfer:cancel',
        SUCCESS: 'transfer:success',
        LOADING: 'transfer:loading',
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
            .name('transfer_id')
            .label('Transfer id')
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
        let warehouseUrl = `api/branches/${session['branch_id']}/warehouses`;
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('almacen_origen')
            .label('Almacen origen')
            .url(warehouseUrl)
            .valueField('almacen_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('origen')
            .label('Almacen origen')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('almacen_destino')
            .label('Almacen destino')
            .url(warehouseUrl)
            .valueField('almacen_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('destino')
            .label('Almacen destino')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .width(100)
            .name('nota')
            .label('Nota')
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
            title: 'Transfer',
            url: 'transfer/get',
            routes: Transfer.ROUTES,
            events: Transfer.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 750,
            height: 450,
            events: Transfer.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Transfer.ROUTES,
            events: Transfer.EVENTS,
            detailFields: this.detailFields,
        });
    }
}