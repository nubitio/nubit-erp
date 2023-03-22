import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";

export default class {{module}} {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/{{kebabModule}}s';

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

{{detailFields}}
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
            detailUrl: {{module}}.API_URL + '/{id}/details',
            detailFields: this.detailFields
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 900,
            height: 600,
            events: {{module}}.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: {{module}}.API_URL,
            events: {{module}}.EVENTS,
            detailFields: this.detailFields,
        });
    }
}