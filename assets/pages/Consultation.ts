import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Drawer} from "../core/drawer/Drawer";
import ConsultationSale from "./ConsultationSale";
import {messenger} from "../core/utils/Messenger";
import {bus} from "../core/event/EventBus";
import {Constants} from "../core/common/Constants";

export default class Consultation {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private paymentsDrawer: Drawer;

    static readonly API_URL = Constants.API_BASE_URL + 'consultations';

    static readonly EVENTS = {
        ADD: 'consultation:add',
        EDIT: 'consultation:edit',
        DELETE: 'consultation:delete',
        SAVE: 'consultation:save',
        CANCEL: 'consultation:cancel',
        SUCCESS: 'consultation:success',
        LOADING: 'consultation:loading',
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
            .name('id')
            .label('Id')
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('patient')
            .label('Paciente')
            .url(Constants.API_BASE_URL + 'patients')
            .mode('remote')
            .valueField('id')
            .textField('razon_social')
            .formatter((value: any) => value?.full_name)
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('physician')
            .label('Médico')
            .url(Constants.API_BASE_URL + 'physicians')
            .valueField('id')
            .textField('full_name')
            .formatter((value: any) => value?.full_name+'<br>'+value?.specialty?.name)
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('date')
            .label('Fecha')
            .formatter(SharedUtils.dateFormatter)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('reason')
            .label('Motivo')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXTAREA)
            .name('diagnosis')
            .label('Diagnóstico')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXTAREA)
            .name('treatment')
            .label('Tratamiento')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXTAREA)
            .name('notes')
            .label('Notas')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .readonly(true)
            .name('queue')
            .label('Turno')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.renderPayments();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Consultas',
            url: Consultation.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            toolbar: [{
                text: 'Pagos',
                iconCls: 'fas fa-receipt fa-1x text-info',
                handler: () => {
                    let selected = this.dataGrid.selectedRow;
                    if (!selected) {
                        messenger.show('Info', 'Debe seleccionar una consulta');
                        return;
                    }
                    bus.emit('consultation:select', selected)
                    this.paymentsDrawer.show();
                }
            }],
            events: Consultation.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Consultation.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Consultation.API_URL,
            events: Consultation.EVENTS,
        });
    }

    renderPayments() {
        const consultationSale = new ConsultationSale(this.container);

        this.paymentsDrawer = new Drawer({
            width: 500,
            events: ConsultationSale.EVENTS,
        });

        consultationSale.renderDataGrid(this.paymentsDrawer.id, {
            title: 'Pagos',
            pagination: false,
        })
        consultationSale.renderDialog(this.container)
    }
}
