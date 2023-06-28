import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {Field} from "../core/field/Field";
import {Constants} from "../core/common/Constants";
import {categoryFields} from "./Category/CategoryFields";

export default class Category {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'categories';

    static readonly EVENTS = {
        ADD: 'category:add',
        EDIT: 'category:edit',
        DELETE: 'category:delete',
        SAVE: 'category:save',
        CANCEL: 'category:cancel',
        SUCCESS: 'category:success',
        LOADING: 'category:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields = categoryFields();
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Categorias',
            url: Category.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Category.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Category.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            url: Category.API_URL,
            events: Category.EVENTS,
        });
    }
}
