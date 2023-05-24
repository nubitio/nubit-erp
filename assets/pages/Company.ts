import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";
import {ConsultService} from "../services/consult.service";
import {bus} from "../core/event/EventBus";

export default class Company {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private consultService: ConsultService = new ConsultService();

    static readonly API_URL = Constants.API_BASE_URL + 'companies';

    static readonly EVENTS = {
        ADD: 'company:add',
        EDIT: 'company:edit',
        DELETE: 'company:delete',
        SAVE: 'company:save',
        CANCEL: 'company:cancel',
        SUCCESS: 'company:success',
        LOADING: 'company:loading',
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
            .name('empresa_id')
            .label('Empresa id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('ruc')
            .label('Ruc')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('razon_social')
            .label('Razon social')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('nombre_comercial')
            .label('Nombre comercial')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('domicilio_fiscal')
            .label('Domicilio fiscal')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('telefono')
            .label('Telefono')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.FILE)
            .name('logo')
            .label('Logo')
            .formatter((val: any, row: any) => SharedUtils.imageFormatter(val?.path, row['razon_social']))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.setupObservers();
    }

    setupObservers() {
        bus.on(Company.EVENTS.SUCCESS, () => bus.emit('app:reload'));
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Company',
            url: Company.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Company.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: Company.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Company.API_URL,
            events: Company.EVENTS,
        });

        let rucField = this.form.getField('ruc').textbox({
            icons: [{
                iconCls: Constants.ICON_SEARCH,
                handler: () => ref.getRucInfo(rucField.textbox('getValue'))
            }]
        });

        rucField.textbox('textbox').bind('keyup', function (e: any) {
            if (e.keyCode === 13) {
                ref.getRucInfo(rucField.textbox('getValue'));
            }
        });
    }

    getRucInfo(ruc: string) {
        this.consultService.consultRuc(ruc)
            .subscribe((response) => {
                this.form.getField('razon_social').textbox('setValue', response.data.name);
                this.form.getField('domicilio_fiscal').textbox('setValue', response.data.address);
            });
    }
}
