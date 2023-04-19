import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";
import {bus} from "../core/event/EventBus";
import {ConsultService} from "../services/consult.service";

export default class Supplier {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private consultService: ConsultService = new ConsultService();

    static readonly API_URL = 'api/suppliers';

    static readonly EVENTS = {
        ADD: 'supplier:add',
        EDIT: 'supplier:edit',
        DELETE: 'supplier:delete',
        SAVE: 'supplier:save',
        CANCEL: 'supplier:cancel',
        SUCCESS: 'supplier:success',
        LOADING: 'supplier:loading',
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
            .name('proveedor_id')
            .label('Proveedor id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('ruc')
            .label('Ruc')
            .validators(['length[11, 11]'])
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXTAREA)
            .name('razon_social')
            .label('Proveedor')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('domicilio_fiscal')
            .label('Dirección')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('telefono')
            .label('Telefono')
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
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Supplier',
            url: Supplier.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Supplier.EVENTS,
        });
    }

    renderDialog() {
        let ref = this;
        this.dialog = new Dialog(this.container, {
            events: Supplier.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Supplier.API_URL,
            events: Supplier.EVENTS,
        });

        let rucField = this.form.getField('ruc').textbox({
            icons: [{
                iconCls: Constants.ICON_SEARCH,
                handler: () => ref.getRucInfo(rucField.textbox('getValue'))
            }]
        })

        rucField.textbox('textbox').bind('keyup', function (e: any) {
            if (e.keyCode === 13) {
                ref.getRucInfo(rucField.textbox('getValue'));
            }
        });
    }

    private getRucInfo(ruc: string) {
        bus.emit(Supplier.EVENTS.LOADING, true);
        this.consultService.consultRuc(ruc)
            .subscribe((response) => {
                this.form.getField('razon_social').textbox('setValue', response.data.name);
                this.form.getField('domicilio_fiscal').textbox('setValue', response.data.address);
            }).add(() => bus.emit(Supplier.EVENTS.LOADING, false));
    }
}