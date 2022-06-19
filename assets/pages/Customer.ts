import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";

export default class Customer {
    private container: string;
    private fields: any = {};
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

    constructor(container, options) {
        this.container = container;
        this.dataGrid = null;
        this.dialog = null;
        this.form = null;
        this.options = options;
        this.init();
    }

    init() {
        this.fields = [
            {
                type: FormType.TEXT,
                label: 'RUC/DNI',
                name: 'ruc',
                required: true,
                validators: ['validCustomerId'],
                isKey: true,
                visible: true
            },
            {
                type: FormType.TEXTAREA,
                label: 'Cliente',
                name: 'razon_social',
                required: true,
                width: 100,
                visible: true
            },
            {
                type: FormType.TEXTAREA,
                label: 'Domicilio',
                name: 'domicilio_fiscal',
                required: false,
                visible: true
            },
            {type: FormType.TEXT, label: 'Teléfono', name: 'telefono', required: false, visible: true},
            {type: FormType.TEXT, label: 'Correo', name: 'email', required: false, visible: true},
            {
                type: FormType.SWITCH,
                label: 'Activo',
                name: 'estado',
                required: true,
                visible: true,
                formatter: SharedUtils.activeFormatter,
                defaultValue: true
            },
        ];
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
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);

        let rucField = this.form.field.ruc.textbox({
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