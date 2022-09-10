import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {ConsultService} from "../services/consult.service";

export default class Company {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};
    private consultService: ConsultService = new ConsultService();

    public ROUTES = {
        ADD: 'company/add',
        EDIT: 'company/edit/',
        DELETE: 'company/delete/',
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
            .visible(false)
            .type(FormType.HIDDEN)
            .name('empresa_id')
            .label('Empresa id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('ruc')
            .label('Ruc')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('razon_social')
            .label('Razon social')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('nombre_comercial')
            .label('Nombre comercial')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('domicilio_fiscal')
            .label('Domicilio fiscal')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('telefono')
            .label('Telefono')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.FILE)
            .name('logo')
            .label('Logo')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
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
            title: 'Empresas',
            url: 'company/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);

        let rucField = this.form.getField('ruc').textbox({
            icons: [{
                iconCls: Constants.ICON_SEARCH,
                handler: function (e) {
                    ref.getRucInfo(rucField.textbox('getValue'));
                }
            }]
        });

        rucField.textbox('textbox').bind('keyup', function (e) {
            if (e.keyCode === 13) {
                ref.getRucInfo(rucField.textbox('getValue'));
            }
        });
    }

    getRucInfo(ruc: string) {
        this.consultService.consultRuc(ruc)
            .subscribe((data) => {
                this.form.getField('razon_social').textbox('setValue', data.razonSocial);
                this.form.getField('nombre_comercial').textbox('setValue', data.nombreComercial);
                this.form.getField('domicilio_fiscal').textbox('setValue', data.direccion);
            });
    }
}