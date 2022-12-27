import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class CreditNote {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'credit-note/add',
        EDIT: 'credit-note/edit/',
        DELETE: 'credit-note/delete/',
    }

    static readonly EVENTS = {
        ADD: 'credit-note:add',
        EDIT: 'credit-note:edit',
        DELETE: 'credit-note:delete',
        SAVE: 'credit-note:save',
        CANCEL: 'credit-note:cancel',
        SUCCESS: 'credit-note:success',
        LOADING: 'credit-note:loading',
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
            .name('nota_id')
            .label('Nota id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('movimiento_id')
            .label('Comprobante')
            .visible(false)
            .mode('remote')
            .url('api/invoice')
            .textField('comprobante')
            .valueField('movimiento_id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('tipo_id')
            .label('Tipo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SELECT)
            .name('codigo_motivo')
            .label('Codigo motivo')
            .data(
                [{
                    value: '01',
                    text: 'Anulación de la operación'
                }, {
                    value: '02',
                    text: 'Anulación por error en el RUC'
                }, {
                    value: '03',
                    text: 'Corrección por error en la descripción'
                }, {
                    value: '04',
                    text: 'Descuento global'
                }, {
                    value: '05',
                    text: 'Descuento por ítem'
                }, {
                    value: '06',
                    text: 'Devolución total'
                }, {
                    value: '07',
                    text: 'Devolución por ítem'
                }, {
                    value: '08',
                    text: 'Bonificación'
                }, {
                    value: '09',
                    text: 'Disminución en el valor'
                }, {
                    value: '10',
                    text: 'Otros Conceptos'
                }]
            )
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('descripcion_motivo')
            .label('Descripcion motivo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('serie')
            .label('Serie')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('correlativo')
            .label('Numero')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('doc_afectado')
            .label('Doc. afectado')
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('envio_sunat')
            .label('Envio sunat')
            .align('center')
            .formatter(SharedUtils.billStatusFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('mensaje_sunat')
            .label('Mensaje sunat')
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'CreditNote',
            url: 'credit-note/get',
            routes: CreditNote.ROUTES,
            events: CreditNote.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: CreditNote.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: CreditNote.ROUTES,
            events: CreditNote.EVENTS,
        });
    }
}