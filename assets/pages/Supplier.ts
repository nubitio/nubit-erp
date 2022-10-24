import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";
import {Constants} from "../Constants";
import {ConsultService} from "../services/consult.service";
import {bus} from "../EventBus";
import {Event} from "../Event";

export default class Supplier {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private consultService: ConsultService = new ConsultService();

    static readonly ROUTES = {
        ADD: 'supplier/add',
        EDIT: 'supplier/edit/',
        DELETE: 'supplier/delete/',
        EXPORT: 'supplier/export',
    }

    static readonly EVENTS = {
        ADD: 'supplier:add',
        EDIT: 'supplier:edit',
        DELETE: 'supplier:delete',
        SAVE: 'supplier:save',
        CANCEL: 'supplier:cancel',
        SUCCESS: 'supplier:success',
        LOADING: 'supplier:loading',
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
            .name('proveedor_id')
            .label('Proveedor id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('ruc')
            .label('Ruc')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('razon_social')
            .label('Proveedor')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('domicilio_fiscal')
            .label('Dirección')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('telefono')
            .label('Telefono')
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
            title: 'Supplier',
            url: 'supplier/get',
            routes: Supplier.ROUTES,
            events: Supplier.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: Supplier.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Supplier.ROUTES,
            events: Supplier.EVENTS,
        });

        let rucField = this.form.getField('ruc').textbox({
            icons: [{
                iconCls: Constants.ICON_SEARCH,
                handler: function (e) {
                    ref.getRucInfo(rucField.textbox('getValue'));
                }
            }]
        })

        rucField.textbox('textbox').bind('keyup', function (e) {
            if (e.keyCode === 13) {
                ref.getRucInfo(rucField.textbox('getValue'));
            }
        });
    }

    private getRucInfo(ruc: string) {
        bus.emit(new Event(Supplier.EVENTS.LOADING, true));
        this.consultService.consultRuc(ruc)
            .subscribe((data) => {
                this.form.getField('razon_social').textbox('setValue', data.razonSocial);
                this.form.getField('domicilio_fiscal').textbox('setValue', data.direccion);
                bus.emit(new Event(Supplier.EVENTS.LOADING, false));
            });
    }
}