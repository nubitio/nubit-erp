import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import NumberUtils from "../core/utils/NumberUtils";
import {bus} from "../core/event/EventBus";
import FilterRule from "../core/common/FilterRule";

export default class ConsultationSale {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/consultation-sales';

    static readonly EVENTS = {
        ADD: 'consultation-sale:add',
        EDIT: 'consultation-sale:edit',
        DELETE: 'consultation-sale:delete',
        SAVE: 'consultation-sale:save',
        CANCEL: 'consultation-sale:cancel',
        SUCCESS: 'consultation-sale:success',
        LOADING: 'consultation-sale:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
        this.setupObservers();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('consultation')
            .label('Consultation')
            .url('api/consultations')
            .valueField('id')
            .textField('id')
            .valueType('number')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('sale')
            .label('Sale')
            .url('api/sales')
            .valueField('movimiento_id')
            .textField('numero')
            .formatter((row: any) => `<b>Voucher: ${row.serie}-${row.numero}</b><br><b>C.P:</b> ${row.invoice??''}<br><b>Total:</b> ${NumberUtils.formatCurrency(row.total)}`)
            .valueType('string')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('numero')
            .label('Voucher')
            .width(100)
            .formatter((value: any, row: any) => SharedUtils.voucherFormatter(row.sale, row.sale))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('invoice')
            .label('Comprobante')
            .formatter((value: any, row: any) => SharedUtils.invoiceFormatter(row.sale.invoice, row.sale))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('total')
            .label('Total')
            .align('right')
            .formatter((value: any, row: any) => NumberUtils.formatCurrency(row.sale.total))
            .build());
    };

    render() {
        this.renderDataGrid(this.container);
        this.renderDialog(this.container);
    }

    renderDataGrid(container: string, options?: any) {
        let defaultOptions = {
            title: 'ConsultationSale',
            url: ConsultationSale.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: ConsultationSale.EVENTS,
        }

        this.dataGrid = new DataGrid(container, this.fields, Object.assign(defaultOptions, options));
    }

    renderDialog(container: string) {
        this.dialog = new Dialog(container, {
            events: ConsultationSale.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: ConsultationSale.API_URL,
            events: ConsultationSale.EVENTS,
        });
    }

    setupObservers() {
        bus.on('consultation:select', (data: any) => {
            let consultationField = this.fields.find(field => field.name === 'consultation');
            if (consultationField) {
                consultationField.defaultValue = data.id;
                this.form.getField('consultation').combobox('hide');
            }
            let saleField = this.fields.find(field => field.name === 'sale');
            if (saleField) {
                let filterRule: FilterRule = {
                    field: 'c.ruc',
                    op: 'equal',
                    value: data.patient.customer.ruc
                }
                saleField.url = `api/sales?filterRules=[${JSON.stringify(filterRule)}]`;
                this.form.getField('sale').combobox('reload');
            }
            let patientFilter: FilterRule = {
                field: 'c.patient',
                op: 'equal',
                value: data.patient.id
            }
            this.dataGrid.addFilterRule(patientFilter);
        });
    }
}