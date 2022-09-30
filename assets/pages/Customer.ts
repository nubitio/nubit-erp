import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Customer {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'cliente/add/',
        EDIT: 'cliente/edit/',
        DELETE: 'cliente/delete/',
        EXPORT: 'cliente/exportar/',
    }

    static EVENTS = {
        ADD: 'customer:add',
        EDIT: 'customer:edit',
        DELETE: 'customer:delete',
        SAVE: 'customer:save',
        CANCEL: 'customer:cancel',
        SUCCESS: 'customer:success',
        LOADING: 'customer:loading',
    }

    constructor(container, options) {
        this.container = container;
        this.dataGrid = null;
        this.dialog = null;
        this.form = null;
        this.options = options;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .name('ruc')
            .label('RUC/DNI')
            .validators(['validCustomerId'])
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('razon_social')
            .label('Cliente')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('domicilio_fiscal')
            .label('Domicilio')
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
            .formatter(SharedUtils.activeFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Clientes',
            url: 'cliente/get',
            routes: this.ROUTES,
            events: Customer.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: Customer.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: this.ROUTES,
            events: Customer.EVENTS
        });

        let rucField = this.form.getField('ruc').textbox({
            icons: [{
                iconCls: Constants.ICON_SEARCH,
                handler: function (e) {
                    ref.consult()
                }
            }]
        });

        rucField.textbox('textbox').bind('keyup', function (e) {
            if (e.keyCode === 13) {
                ref.consult()
            }
        });
    }

    consult() {
        let doc = this.form.field.ruc.textbox('getValue');
        let apiUrl = ''
        let dialogTitle = ''
        if (doc.length === 11) {
            dialogTitle = 'Consultando RUC';
            apiUrl = 'api/ruc/';
        } else {
            dialogTitle = 'Consultando DNI';
            apiUrl = 'api/dni/'
        }
        this.dialog.setLoading(true, dialogTitle);
        fetch(apiUrl + doc)
            .then(response => response.json())
            .then(data => {
                    this.dialog.setLoading(false);
                    if (doc.length === 11) {
                        if (data.estado === 'ACTIVO') {
                            this.form.field.razon_social.textbox('setValue', data.razonSocial);
                            this.form.field.domicilio_fiscal.textbox('setValue', data.direccion);
                        } else {
                            SharedUtils.showMessage('Error', 'El RUC no está activo');
                        }
                    } else {
                        this.form.field.razon_social.textbox('setValue', data.nombres + ' ' + data.apellidoPaterno + ' ' + data.apellidoMaterno);
                    }
                }
            )
            .catch(error => {
                this.dialog.setLoading(false);
                SharedUtils.showMessage('Error', error.message);
            });
    }
}