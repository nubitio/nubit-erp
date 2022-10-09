import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Cashbox {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'cashbox-detail/add',
        EDIT: 'cashbox-detail/edit/',
        DELETE: 'cashbox-detail/delete/',
    }

    static readonly EVENTS = {
        ADD: 'cashbox:add',
        EDIT: 'cashbox:edit',
        DELETE: 'cashbox:delete',
        SAVE: 'cashbox:save',
        CANCEL: 'cashbox:cancel',
        SUCCESS: 'cashbox:success',
        LOADING: 'cashbox:loading',
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
            .name('caja_id')
            .label('Caja id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENUM)
            .name('tipo_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'INGRESO'},
                {value: 2, text: 'EGRESO'},
            ])
            .formatter(this.typeFormatter)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Concepto')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('referencia')
            .label('Observaciones')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('monto')
            .label('Monto')
            .align('right')
            .required(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        let toolbar = [
            {
                text: 'Cerrar caja',
                iconCls: 'fa fa-lock fa-1x text-info',
                handler: () => {

                }
            }
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Cashbox',
            url: 'cashbox/last',
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
            toolbar: toolbar,
            pagination: false,
            showFooter: true,
            filter: false,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Cashbox.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
        });
    }

    private typeFormatter(value, row, index) {
        value = Number(value);
        if (value === 1) {
            value = '<span class="badge rounded-pill bg-success">INGRESO</span>';
        } else if (value === 2) {
            value = '<span class="badge rounded-pill bg-danger">EGRESO</span>';
        } else {
            value = '';
        }
        return value;
    }
}