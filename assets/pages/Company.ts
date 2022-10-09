import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {ConsultService} from "../services/consult.service";

export default class Company {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private consultService: ConsultService = new ConsultService();

    static readonly ROUTES = {
        ADD: 'company/add',
        EDIT: 'company/edit/',
        DELETE: 'company/delete/',
    }

    static readonly EVENTS = {
        ADD: 'company:add',
        EDIT: 'company:edit',
        DELETE: 'company:delete',
        SAVE: 'company:save',
        CANCEL: 'company:cancel',
        SUCCESS: 'company:success',
        LOADING: 'company:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('empresa_id')
            .label('Empresa id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('ruc')
            .label('Ruc')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('razon_social')
            .label('Razon social')
            .required(true)
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
            .width(100)
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
            .formatter(function (val, row) {
                return SharedUtils.imageFormatter(val, row['razon_social'], 'uploads/images/');
            })
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
            routes: Company.ROUTES,
            events: Company.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: Company.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Company.ROUTES,
            events: Company.EVENTS,
        });

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