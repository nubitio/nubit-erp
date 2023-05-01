import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import NumberUtils from "../core/utils/NumberUtils";

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
            .type(FieldType.NUMBER)
            .name('consultation_id')
            .label('Consultation id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('sale_id')
            .label('Sale id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('numero')
            .label('Voucher')
            .formatter(SharedUtils.voucherFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('invoice')
            .label('Comprobante')
            .formatter(SharedUtils.invoiceFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('total')
            .label('Total')
            .align('right')
            .formatter((value: any) => NumberUtils.formatCurrency(value))
            .build());
    };

    render() {
        this.renderDataGrid(this.container);
        this.renderDialog(this.container);
    }

    renderDataGrid(container: string) {
        this.dataGrid = new DataGrid(container, this.fields, {
            title: 'ConsultationSale',
            url: ConsultationSale.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: ConsultationSale.EVENTS,
        });
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
}