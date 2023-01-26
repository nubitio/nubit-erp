import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {ConsultService} from "../services/consult.service";
import {Constants} from "../core/common/Constants";

export default class Customer {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private consultService = new ConsultService();

    static readonly API_URL = 'api/customers';

    static readonly EVENTS = {
        ADD: 'customer:add',
        EDIT: 'customer:edit',
        DELETE: 'customer:delete',
        SAVE: 'customer:save',
        CANCEL: 'customer:cancel',
        SUCCESS: 'customer:success',
        LOADING: 'customer:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .name('ruc')
            .label('RUC/DNI')
            .validators(['validCustomerId'])
            .required(true)
            .sortable(true)
            .valueType('string')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('razon_social')
            .label('Cliente')
            .required(true)
            .sortable(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('domicilio_fiscal')
            .label('Domicilio')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .name('telefono')
            .label('Teléfono')
            .build());
        this.fields.push(new FieldBuilder()
            .name('email')
            .label('Email')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
            .align('center')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Customer',
            url: Customer.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Customer.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: Customer.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Customer.API_URL,
            events: Customer.EVENTS,
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

    consult() {
        let doc = this.form.getField('ruc').textbox('getValue');
        if (doc.length === 11) {
            this.dialog.setLoading(true, 'Consultando RUC...');
            this.consultService.consultRuc(doc)
                .subscribe((response) => {
                    this.dialog.setLoading(false);
                    if (response.estado === 'ACTIVO') {
                        this.form.getField('razon_social').textbox('setValue', response.razonSocial);
                        this.form.getField('domicilio_fiscal').textbox('setValue', response.direccion);
                    } else {
                        SharedUtils.showMessage('Error', 'El RUC ingresado no se encuentra activo');
                    }
                })
        } else {
            this.dialog.setLoading(true, 'Consultando DNI...');
            this.consultService.consultDni(doc)
                .subscribe((response) => {
                    this.dialog.setLoading(false);
                    this.form.getField('razon_social').textbox('setValue', response.nombres + ' ' + response.apellidoPaterno + ' ' + response.apellidoMaterno);
                })
        }
    }
}