import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Category {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'category/add',
        EDIT: 'category/edit/',
        DELETE: 'category/delete/',
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
            .name('categoria_id')
            .label('Categoria id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('parent_id')
            .label('Categoria superior')
            .url('api/category')
            .valueField('categoria_id')
            .textField('descripcion')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.FILE)
            .name('imagen')
            .label('Imagen')
            .formatter((val, row) => {
                return SharedUtils.imageFormatter(val, row['descripcion'], 'uploads/images/')
            })
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
            title: 'Categorias',
            url: 'category/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}