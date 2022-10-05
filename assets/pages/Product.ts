import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class Product {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'product/add',
        EDIT: 'product/edit/',
        DELETE: 'product/delete/',
    }

    static readonly EVENTS = {
        ADD: 'product:add',
        EDIT: 'product:edit',
        DELETE: 'product:delete',
        SAVE: 'product:save',
        CANCEL: 'product:cancel',
        SUCCESS: 'product:success',
        LOADING: 'product:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.HIDDEN)
            .name('producto_id')
            .label('Producto id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('codigo')
            .label('Codigo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENUM)
            .name('tipo_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'Fisico'},
                {value: 2, text: 'Servicio'},
            ])
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('tipo')
            .label('Tipo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('categoria_id')
            .label('Categoria')
            .url('api/category')
            .valueField('categoria_id')
            .textField('descripcion')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('categoria')
            .label('Categoria')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('precio')
            .label('Precio')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.FILE)
            .name('imagen')
            .label('Imagen')
            .formatter((val, row) => SharedUtils.imageFormatter(val, row['descripcion'], 'uploads/images/'))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.activeFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Product',
            url: 'product/get',
            routes: Product.ROUTES,
            events: Product.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Product.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Product.ROUTES,
            events: Product.EVENTS,
        });
    }
}