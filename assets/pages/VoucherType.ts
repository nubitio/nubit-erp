import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";

export default class VoucherType {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'voucher-types';

    static readonly EVENTS = {
        ADD: 'voucherType:add',
        EDIT: 'voucherType:edit',
        DELETE: 'voucherType:delete',
        SAVE: 'voucherType:save',
        CANCEL: 'voucherType:cancel',
        SUCCESS: 'voucherType:success',
        LOADING: 'voucherType:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .type(FieldType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('codigo')
            .label('Codigo')
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
            title: 'VoucherType',
            url: VoucherType.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: VoucherType.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: VoucherType.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: VoucherType.API_URL,
            events: VoucherType.EVENTS,
        });
    }
}
