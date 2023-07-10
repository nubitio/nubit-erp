import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";

export default class Transfer {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'transfers';

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
            .type(FieldType.NONE)
            .name('transfer_id')
            .label('Transfer id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('fecha')
            .label('Fecha')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('almacen_origen')
            .label('Almacen origen')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('almacen_destino')
            .label('Almacen destino')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('nota')
            .label('Nota')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('salida_id')
            .label('Salida id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('ingreso_id')
            .label('Ingreso id')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Transfer',
            url: Transfer.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Transfer.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Transfer.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Transfer.API_URL,
            events: Transfer.EVENTS,
        });
    }
}
