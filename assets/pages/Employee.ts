import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import NumberUtils from "../core/utils/NumberUtils";
import {SharedUtils} from "../core/utils/SharedUtils";

export default class Employee {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/employees';

    static readonly EVENTS = {
        ADD: 'employee:add',
        EDIT: 'employee:edit',
        DELETE: 'employee:delete',
        SAVE: 'employee:save',
        CANCEL: 'employee:cancel',
        SUCCESS: 'employee:success',
        LOADING: 'employee:loading',
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
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('id_number')
            .label('DNI')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('first_name')
            .label('Nombres')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('last_name')
            .label('Apellidos')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENUM)
            .name('gender')
            .label('Sexo')
            .required(true)
            .data([
                {value: 1, text: "Masculino"},
                {value: 2, text: "Femenino"}
            ])
            .formatter((value: number) => {
                if (value === 1) {
                    return 'Masculino';
                } else if (value === 2) {
                    return 'Femenino';
                } else {
                    return '';
                }
            })
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('birthdate')
            .label('Fecha de nacimiento')
            .formatter(SharedUtils.dateFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('address')
            .label('Dirección')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('phone')
            .label('Teléfono')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('hire_date')
            .label('Fecha de contratación')
            .formatter(SharedUtils.dateFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('position')
            .label('Cargo')
            .url('api/positions')
            .valueField('id')
            .textField('name')
            .formatter((value: any) => value?.name)
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('salary')
            .label('Salario')
            .formatter((value: number) => NumberUtils.formatCurrency(value))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Empleados',
            url: Employee.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Employee.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Employee.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Employee.API_URL,
            events: Employee.EVENTS,
        });
    }
}