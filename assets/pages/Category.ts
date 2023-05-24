import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";

export default class Category {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'categories';

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
            .type(FieldType.NONE)
            .name('categoria_id')
            .label('Categoria id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('parent')
            .label('Categoria padre')
            .url(Constants.API_BASE_URL + 'categories')
            .valueField('categoria_id')
            .textField('descripcion')
            .formatter((value: any, row: any) => {
                return row.parent?.descripcion;
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.FILE)
            .name('imagen')
            .label('Imagen')
            .formatter((val: any, row: any) => {
                return SharedUtils.imageFormatter(val, row['descripcion'], 'uploads/images/')
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
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
