import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class VoucherSeries {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'voucher-series/add',
        EDIT: 'voucher-series/edit/',
        DELETE: 'voucher-series/delete/',
    }

    static readonly EVENTS = {
        ADD: 'voucher-series:add',
        EDIT: 'voucher-series:edit',
        DELETE: 'voucher-series:delete',
        SAVE: 'voucher-series:save',
        CANCEL: 'voucher-series:cancel',
        SUCCESS: 'voucher-series:success',
        LOADING: 'voucher-series:loading',
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
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('local_id')
            .label('Local')
            .url('api/local')
            .valueField('local_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('local')
            .label('Local')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('tipo_comprobante_id')
            .label('Tipo comprobante')
            .url('api/voucher-type')
            .valueField('codigo')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('tipo_comprobante')
            .label('Tipo comprobante')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('serie')
            .label('Serie')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('serie_asociada')
            .label('Serie asociada')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('active')
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
            title: 'VoucherSeries',
            url: 'voucher-series/get',
            routes: VoucherSeries.ROUTES,
            events: VoucherSeries.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: VoucherSeries.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: VoucherSeries.ROUTES,
            events: VoucherSeries.EVENTS,
        });
    }
}