import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";

export default class Recipe {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/recipes';

    static readonly EVENTS = {
        ADD: 'recipe:add',
        EDIT: 'recipe:edit',
        DELETE: 'recipe:delete',
        SAVE: 'recipe:save',
        CANCEL: 'recipe:cancel',
        SUCCESS: 'recipe:success',
        LOADING: 'recipe:loading',
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
            .type(FieldType.TEXT)
            .name('name')
            .label('Name')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Active')
            .build())

        this.detailFields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('recipe_id')
            .label('Recipe id')
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('material_id')
            .label('Material id')
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('quantity')
            .label('Quantity')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Recipe',
            url: Recipe.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Recipe.EVENTS,
            detailUrl: Recipe.API_URL + '/{id}/details',
            detailFields: this.detailFields
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 900,
            height: 600,
            events: Recipe.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Recipe.API_URL,
            events: Recipe.EVENTS,
            detailFields: this.detailFields,
        });
    }
}