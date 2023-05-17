import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {bus} from "../core/event/EventBus";
import {httpClient} from "../core/http/HttpClient";

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
            .valueType('number')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('name')
            .label('Nombre')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build())

        this.detailFields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('material')
            .label('Material')
            .width(100)
            .url('api/products')
            .textField('descripcion')
            .valueField('producto_id')
            .formatter((value: any) => value.descripcion)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('quantity')
            .label('Cantidad')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.setupObservers();
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

    setupObservers() {
        bus.on(Recipe.EVENTS.EDIT, () => {
            let selected = this.dataGrid.selectedRow;
            let recipeId = selected[this.dataGrid.getIdField()]

            httpClient.get<any>(Recipe.API_URL + '/' + recipeId + '/details').subscribe((response) => {
                this.form.loadDetailData(response.data);
            });
        });
    }
}
