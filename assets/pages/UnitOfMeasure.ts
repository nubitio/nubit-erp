import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";

export default class UnitOfMeasure {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'unit-of-measures';

    static readonly EVENTS = {
        ADD: 'unit-of-measure:add',
        EDIT: 'unit-of-measure:edit',
        DELETE: 'unit-of-measure:delete',
        SAVE: 'unit-of-measure:save',
        CANCEL: 'unit-of-measure:cancel',
        SUCCESS: 'unit-of-measure:success',
        LOADING: 'unit-of-measure:loading',
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
            .name('unidad_id')
            .label('Unidad id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('name')
            .label('Name')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('type_id')
            .label('Type id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('ratio')
            .label('Ratio')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('is_reference')
            .label('Is reference')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Active')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'UnitOfMeasure',
            url: UnitOfMeasure.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: UnitOfMeasure.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: UnitOfMeasure.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: UnitOfMeasure.API_URL,
            events: UnitOfMeasure.EVENTS,
        });
    }
}
