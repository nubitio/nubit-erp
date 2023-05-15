import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import NumberUtils from "../core/utils/NumberUtils";
import FilterRule from "../core/common/FilterRule";
import {messenger} from "../core/utils/Messenger";
import {saleService} from "../services/sale.service";

export default class CreditNote {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/credit-notes';

    static readonly EVENTS = {
        ADD: 'credit-note:add',
        EDIT: 'credit-note:edit',
        DELETE: 'credit-note:delete',
        SAVE: 'credit-note:save',
        CANCEL: 'credit-note:cancel',
        SUCCESS: 'credit-note:success',
        LOADING: 'credit-note:loading',
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
            .name('nota_id')
            .label('Nota id')
            .required(true)
            .build());
        let saleFilter: FilterRule = {
            field: 'invoice',
            value: '',
            op: 'isnotnull'
        }
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('sale')
            .label('Comprobante')
            .visible(false)
            .mode('remote')
            .url('api/sales?filterRules=[' + JSON.stringify(saleFilter) + ']')
            .textField('invoice')
            .valueField('movimiento_id')
            .formatter((row: any) => `<b>C.P:</b> ${row.invoice ?? ''}<br><b>Total:</b> ${NumberUtils.formatCurrency(row.total)}`)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('fecha')
            .label('Fecha')
            .formatter(SharedUtils.dateFormatter)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('tipo_id')
            .label('Tipo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SELECT)
            .name('codigo_motivo')
            .label('Código')
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
            .type(FieldType.TEXTAREA)
            .name('descripcion_motivo')
            .label('Motivo Descripción')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('serie')
            .label('Serie')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('correlativo')
            .label('Número')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('sale')
            .label('Doc. afectado')
            .align('center')
            .formatter((value: any) => value?.invoice)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('envio_sunat')
            .label('Envio sunat')
            .align('center')
            .formatter(SharedUtils.billStatusFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('mensaje_sunat')
            .label('Mensaje sunat')
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        let toolbar = [
            {
                text: 'Enviar a SUNAT',
                iconCls: 'fas fa-cloud-upload fa-1x text-info',
                handler: () => {
                    this.send();
                }
            }, {
                text: 'Ver',
                iconCls: 'fas fa-eye fa-1x text-primary',
                handler: () => {
                    this.view();
                }
            }
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'CreditNote',
            url: CreditNote.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: CreditNote.EVENTS,
            toolbar: toolbar,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: CreditNote.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: CreditNote.API_URL,
            events: CreditNote.EVENTS,
        });
    }

    send() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione un registro');
            return;
        }
        /*if (selected['envio_sunat'] === '0') {
            messenger.show('Información', 'El comprobante ya fue enviado a SUNAT');
            return;
        }*/
        this.dataGrid.showLoading('Enviando comprobante a SUNAT...');
        saleService.makeNote(selected['nota_id']).subscribe((data) => {
            messenger.show('Información', 'Comprobante enviado a SUNAT');
            this.dataGrid.reload();
        }).add(() => {
            this.dataGrid.hideLoading();
        });
    }

    view() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione un registro');
            return;
        }
        window.open('invoice/' + selected['nota_id']);
    }
}
