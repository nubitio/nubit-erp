import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";

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
            .type(FieldType.ENTITY)
            .name('local_id')
            .label('Local')
            .url('api/branches')
            .valueField('local_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('local')
            .label('Local')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('tipo_comprobante_id')
            .label('Tipo comprobante')
            .url('api/voucher-types')
            .valueField('codigo')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('tipo_comprobante')
            .label('Tipo comprobante')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('serie')
            .label('Serie')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('serie_asociada')
            .label('Serie asociada')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
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

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: VoucherSeries.ROUTES,
            events: VoucherSeries.EVENTS,
        });
    }
}