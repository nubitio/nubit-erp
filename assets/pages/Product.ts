import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";

export default class Product {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/products';

    static readonly EVENTS = {
        ADD: 'product:add',
        EDIT: 'product:edit',
        DELETE: 'product:delete',
        SAVE: 'product:save',
        CANCEL: 'product:cancel',
        SUCCESS: 'product:success',
        LOADING: 'product:loading',
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
            .required(true)
            .formatter((value: any, row: any) => {
                if (value === 1) return 'Fisico';
                if (value === 2) return 'Servicio';
                return '';
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('categoria')
            .label('Categoria')
            .url('api/categories')
            .valueField('categoria_id')
            .textField('descripcion')
            .required(true)
            .formatter((value: any, row: any) => value.descripcion ?? '')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio')
            .label('Precio')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.FILE)
            .name('imagen')
            .label('Imagen')
            .formatter((val: any, row: any) => SharedUtils.imageFormatter(val?.path, row['descripcion']))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
            .align('center')
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
            title: 'Product',
            url: Product.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Product.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Product.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Product.API_URL,
            events: Product.EVENTS,
        });
    }
}