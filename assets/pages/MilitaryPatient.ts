import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {FieldBuilder} from "../core/field/FieldBuilder";
import Patient from "./Patient";
import {Constants} from "../core/common/Constants";

export default class MilitaryPatient extends Patient {
    protected readonly container: string;
    declare dataGrid: DataGrid;
    declare dialog: Dialog;
    declare form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'military-patients';

    static readonly EVENTS = {
        ADD: 'military-patient:add',
        EDIT: 'military-patient:edit',
        DELETE: 'military-patient:delete',
        SAVE: 'military-patient:save',
        CANCEL: 'military-patient:cancel',
        SUCCESS: 'military-patient:success',
        LOADING: 'military-patient:loading',
    }

    constructor(container: string) {
        super(container);
        this.container = container;
        super.init();
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
            .type(FieldType.ENTITY)
            .name('patient')
            .label('Patient')
            .textField('id')
            .valueField('id')
            .valueType('number')
            .visible(false)
            .hidden(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('cip_code')
            .label('Código CIP')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('rank')
            .label('Grado')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('weapon_code')
            .label('Arma')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Pacientes',
            url: MilitaryPatient.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: MilitaryPatient.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: MilitaryPatient.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: MilitaryPatient.API_URL,
            events: MilitaryPatient.EVENTS,
        });

        let rucField = this.form.getField('ruc').textbox({
            icons: [{
                iconCls: Constants.ICON_SEARCH,
                handler: () => this.consult()
            }]
        })

        rucField.textbox('textbox').bind('keyup', function (e: any) {
            if (e.keyCode === 13) {
                ref.consult()
            }
        });
    }
}
