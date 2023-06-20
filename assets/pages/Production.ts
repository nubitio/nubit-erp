import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";
import {httpClient} from "../core/http/HttpClient";
import Recipe from "./Recipe";
import {bus} from "../core/event/EventBus";

export default class Production {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'productions';

    static readonly EVENTS = {
        ADD: 'production:add',
        EDIT: 'production:edit',
        DELETE: 'production:delete',
        SAVE: 'production:save',
        CANCEL: 'production:cancel',
        SUCCESS: 'production:success',
        LOADING: 'production:loading',
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
            .type(FieldType.ENTITY)
            .name('product')
            .label('Producto')
            .width(100)
            .url(Constants.API_BASE_URL + 'products')
            .mode('remote')
            .valueField('producto_id')
            .textField('descripcion')
            .formatter((value: any) => value?.descripcion)
            .onSelect((record: any, field: any) => {
                let filter = JSON.stringify({
                    'field': 'product',
                    'op': 'equal',
                    'value': record.producto_id
                })

                let recipeUrl = Constants.API_BASE_URL + 'recipes?filterRules=[' + filter + ']';

                let recipeField = this.form.getField('recipe');
                recipeField.combobox('reload', recipeUrl);
                recipeField.combobox('clear');
            })
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('scheduled_date')
            .label('Fecha programada')
            .defaultValue('today')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('quantity')
            .label('Cantidad')
            .precision(2)
            .defaultValue(1)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('recipe')
            .label('Receta')
            .url(Constants.API_BASE_URL + 'recipes')
            .valueField('id')
            .textField('name')
            .formatter((value: any) => value?.name)
            .onSelect((record: any, field: any) => {
                this.dialog.setLoading(true, 'Cargando receta...');
                httpClient.get<any>(Recipe.API_URL + '/' + record.id + '/details').subscribe((response) => {
                    this.form.loadDetailData(response.data);
                }).add(() => this.dialog.setLoading(false));
            })
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('employee')
            .label('Responsable')
            .url(Constants.API_BASE_URL + 'employees')
            .valueField('id')
            .textField('full_name')
            .formatter((value: any) => value?.full_name)
            .required(true)
            .valueType('number')
            .build());
        let filterLine = JSON.stringify({
            'field': '_key',
            'op': 'equal',
            'value': 'linea'
        });
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('production_line')
            .label('Linea de producción')
            .url(Constants.API_BASE_URL + 'combo-options?filterRules=[' + filterLine + ']')
            .valueType('number')
            .textField('_value')
            .valueField('id')
            .formatter((value: any) => value?._value)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENUM)
            .name('status')
            .label('Estado')
            .data([
                {value: 1, text: "Pendiente"},
                {value: 2, text: "En proceso"},
                {value: 3, text: "Finalizado"},
            ])
            .formatter((value: any) => {
                switch (value) {
                    case 1:
                        return 'Pendiente';
                    case 2:
                        return 'En proceso';
                    case 3:
                        return 'Finalizado';
                }
            })
            .defaultValue(1)
            .build())

        this.detailFields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('material')
            .label('Material')
            .width(100)
            .url(Constants.API_BASE_URL + 'products')
            .textField('descripcion')
            .valueField('producto_id')
            .formatter((value: any) => value.descripcion)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('quantity')
            .label('Cantidad')
            .precision(2)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.setupObservers();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Production',
            url: Production.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Production.EVENTS,
            detailUrl: Production.API_URL + '/{id}/details',
            detailFields: this.detailFields
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 900,
            height: 600,
            events: Production.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Production.API_URL,
            events: Production.EVENTS,
            detailFields: this.detailFields,
        });
    }

    setupObservers() {
        bus.on(Production.EVENTS.EDIT, () => {
            let selected = this.dataGrid.selectedRow;
            let productionId = selected[this.dataGrid.getIdField()]

            httpClient.get<any>(Production.API_URL + '/' + productionId + '/details').subscribe((response) => {
                this.form.loadDetailData(response.data);
            });
        });
    }
}
