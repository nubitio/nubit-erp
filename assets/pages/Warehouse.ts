import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";

export default class Warehouse {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'warehouses';

    static readonly EVENTS = {
        ADD: 'warehouse:add',
        EDIT: 'warehouse:edit',
        DELETE: 'warehouse:delete',
        SAVE: 'warehouse:save',
        CANCEL: 'warehouse:cancel',
        SUCCESS: 'warehouse:success',
        LOADING: 'warehouse:loading',
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
            .name('almacen_id')
            .label('Almacen id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('local')
            .label('Local')
            .url(Constants.API_BASE_URL + 'branches')
            .valueField('local_id')
            .textField('descripcion')
            .required(true)
            .formatter((value: any, row: any) => value.descripcion)
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
            .name('is_default')
            .label('Default')
            .formatter(SharedUtils.booleanFormatter)
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .align('center')
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Warehouse',
            url: Warehouse.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Warehouse.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Warehouse.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Warehouse.API_URL,
            events: Warehouse.EVENTS,
        });
    }
}
