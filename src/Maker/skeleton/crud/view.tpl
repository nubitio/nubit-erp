import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class {{module}} {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: '{{kebabModule}}/add',
        EDIT: '{{kebabModule}}/edit/',
        DELETE: '{{kebabModule}}/delete/',
    }

    static readonly EVENTS = {
        ADD: '{{kebabModule}}:add',
        EDIT: '{{kebabModule}}:edit',
        DELETE: '{{kebabModule}}:delete',
        SAVE: '{{kebabModule}}:save',
        CANCEL: '{{kebabModule}}:cancel',
        SUCCESS: '{{kebabModule}}:success',
        LOADING: '{{kebabModule}}:loading',
    }

    constructor(container) {
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
            url: '{{kebabModule}}/get',
            routes: {{module}}.ROUTES,
            events: {{module}}.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: {{module}}.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: {{module}}.ROUTES,
            events: {{module}}.EVENTS,
        });
    }
}