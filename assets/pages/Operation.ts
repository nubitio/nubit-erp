import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class Operation {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'operation/add',
        EDIT: 'operation/edit/',
        DELETE: 'operation/delete/',
    }

    static readonly EVENTS = {
        ADD: 'operation:add',
        EDIT: 'operation:edit',
        DELETE: 'operation:delete',
        SAVE: 'operation:save',
        CANCEL: 'operation:cancel',
        SUCCESS: 'operation:success',
        LOADING: 'operation:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('operacion_id')
            .label('Operacion id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENUM)
            .name('tipo_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'Entrada'},
                {value: 2, text: 'Salida'},
            ])
            .formatter((value) => value === 1 ? 'Entrada' : 'Salida')
            .required(true)
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
            title: 'Operation',
            url: 'operation/get',
            routes: Operation.ROUTES,
            events: Operation.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Operation.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Operation.ROUTES,
            events: Operation.EVENTS,
        });
    }
}