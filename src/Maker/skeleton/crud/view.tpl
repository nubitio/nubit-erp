import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";

export default class {{module}} {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + '{{kebabModule}}s';

    static readonly EVENTS = {
        ADD: '{{kebabModule}}:add',
        EDIT: '{{kebabModule}}:edit',
        DELETE: '{{kebabModule}}:delete',
        SAVE: '{{kebabModule}}:save',
        CANCEL: '{{kebabModule}}:cancel',
        SUCCESS: '{{kebabModule}}:success',
        LOADING: '{{kebabModule}}:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
{{fields}}
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: '{{module}}',
            url: {{module}}.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: {{module}}.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: {{module}}.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: {{module}}.API_URL,
            events: {{module}}.EVENTS,
        });
    }
}
