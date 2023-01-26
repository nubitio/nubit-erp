import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";

export default class Category {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/categories';

    static readonly EVENTS = {
        ADD: 'category:add',
        EDIT: 'category:edit',
        DELETE: 'category:delete',
        SAVE: 'category:save',
        CANCEL: 'category:cancel',
        SUCCESS: 'category:success',
        LOADING: 'category:loading',
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
            .name('parent')
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
            .formatter((val: any, row: any) => {
                return SharedUtils.imageFormatter(val, row['descripcion'], 'uploads/images/')
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
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
            url: Category.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Category.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Category.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            url: Category.API_URL,
            events: Category.EVENTS,
        });
    }
}