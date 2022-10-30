import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {ConsultService} from "../services/consult.service";

export default class Customer {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private consultService = new ConsultService();

    public readonly ROUTES = {
        ADD: 'customer/add',
        EDIT: 'customer/edit/',
        DELETE: 'customer/delete/',
        EXPORT: 'customer/export',
    }

    static readonly EVENTS = {
        ADD: 'customer:add',
        EDIT: 'customer:edit',
        DELETE: 'customer:delete',
        SAVE: 'customer:save',
        CANCEL: 'customer:cancel',
        SUCCESS: 'customer:success',
        LOADING: 'customer:loading',
    }

    constructor(container) {
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
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('razon_social')
            .label('Cliente')
            .required(true)
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
            url: 'customer/get',
            routes: this.ROUTES,
            events: Customer.EVENTS,
        });
    }

    renderDialog(options: any = {}) {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            width: options.width ?? 400,
            height: options.height ?? 550,
            modal: options.modal ?? true,
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