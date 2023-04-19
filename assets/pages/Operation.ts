import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";

export default class Operation {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/operations';

    static readonly EVENTS = {
        ADD: 'operation:add',
        EDIT: 'operation:edit',
        DELETE: 'operation:delete',
        SAVE: 'operation:save',
        CANCEL: 'operation:cancel',
        SUCCESS: 'operation:success',
        LOADING: 'operation:loading',
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
            .name('operacion_id')
            .label('Operacion id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENUM)
            .name('tipo_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'Entrada'},
                {value: 2, text: 'Salida'},
            ])
            .formatter((value: number) => value === 1 ? 'Entrada' : 'Salida')
            .required(true)
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
            title: 'Operation',
            url: Operation.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Operation.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Operation.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Operation.API_URL,
            events: Operation.EVENTS,
        });
    }
}