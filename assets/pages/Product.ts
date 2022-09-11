import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Product {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'product/add',
        EDIT: 'product/edit/',
        DELETE: 'product/delete/',
    }

    constructor(container, options) {
        this.container = container;
        this.dataGrid = null;
        this.dialog = null;
        this.form = null;
        this.options = options;
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
            .type(FormType.NUMBER)
            .name('tipo_id')
            .label('Tipo id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('categoria_id')
            .label('Categoria id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('unidad_id')
            .label('Unidad id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('destino_id')
            .label('Destino id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('stock_minimo')
            .label('Stock minimo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('precio')
            .label('Precio')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('precio_compra')
            .label('Precio compra')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('imagen')
            .label('Imagen')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('estado')
            .label('Estado')
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
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}