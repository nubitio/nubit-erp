import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";

export default class ComboOption {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'combo-options';

    static readonly EVENTS = {
        ADD: 'combo-option:add',
        EDIT: 'combo-option:edit',
        DELETE: 'combo-option:delete',
        SAVE: 'combo-option:save',
        CANCEL: 'combo-option:cancel',
        SUCCESS: 'combo-option:success',
        LOADING: 'combo-option:loading',
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
            .valueType('number')
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('_key')
            .width(50)
            .label(' key')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('_value')
            .width(50)
            .label(' value')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Active')
            .defaultValue('true')
            .formatter(SharedUtils.booleanFormatter)
            .required(true)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'ComboOption',
            url: ComboOption.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: ComboOption.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: ComboOption.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: ComboOption.API_URL,
            events: ComboOption.EVENTS,
        });
    }
}
