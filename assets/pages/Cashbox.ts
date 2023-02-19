import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {messenger} from "../core/utils/Messenger";
import {httpClient} from "../core/http/HttpClient";
import {bus} from "../core/event/EventBus";
import {EventObject} from "../core/event/EventObject";

export default class Cashbox {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'cashbox-detail/add',
        EDIT: 'cashbox-detail/edit/',
        DELETE: 'cashbox-detail/delete/',
    }

    static readonly EVENTS = {
        ADD: 'cashbox:add',
        EDIT: 'cashbox:edit',
        DELETE: 'cashbox:delete',
        SAVE: 'cashbox:save',
        CANCEL: 'cashbox:cancel',
        SUCCESS: 'cashbox:success',
        LOADING: 'cashbox:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .visible(false)
            .type(FormType.TEXT)
            .name('caja_id')
            .label('Caja')
            .hidden(true)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENUM)
            .name('tipo_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'INGRESO'},
                {value: 2, text: 'EGRESO'},
            ])
            .formatter(this.typeFormatter)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Concepto')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('referencia')
            .label('Observaciones')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('monto')
            .label('Monto')
            .align('right')
            .required(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.setupOpenCashBox();
    }

    renderDataGrid() {
        let toolbar = [
            {
                text: 'Cerrar caja',
                iconCls: 'fa fa-lock fa-1x text-info',
                handler: () => {
                    this.closeCashbox();
                }
            }
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Cashbox',
            url: 'cashbox/last',
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
            toolbar: toolbar,
            pagination: false,
            showFooter: true,
            filter: false,
            onLoadSuccess: (data: any) => {
                this.fields.forEach(field => {
                    if (field.name === 'caja_id') {
                        field.defaultValue = data['header']['caja_id'];
                    }
                });
                this.checkCashbox();
            }
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Cashbox.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
        });
    }

    private typeFormatter(value: number) {
        value = Number(value);
        let text = '';
        if (value === 1) {
            text = '<span class="badge rounded-pill bg-success">INGRESO</span>';
        } else if (value === 2) {
            text = '<span class="badge rounded-pill bg-danger">EGRESO</span>';
        }
        return text;
    }

    private checkCashbox(): void {
        let cashboxId = this.dataGrid.data['header']['caja_id'];
        if (cashboxId === undefined) {
            bus.emit('cashbox-open:add');
        }
    }

    private setupOpenCashBox() {
        let initialAmount = 0;
        let events = {
            ADD: 'cashbox-open:add',
            EDIT: 'cashbox-open:edit',
            DELETE: 'cashbox-open:delete',
            SAVE: 'cashbox-open:save',
            CANCEL: 'cashbox-open:cancel',
            SUCCESS: 'cashbox-open:success',
            LOADING: 'cashbox-open:loading',
        }
        let dialog = new Dialog(this.container, {
            events: events,
        });
        let form = new Form(dialog.id ?? '', [
            new FieldBuilder()
                .type(FormType.CURRENCY)
                .name('monto_inicial')
                .label('Monto inicial')
                .required(true)
                .defaultValue(initialAmount)
                .build(),
        ]);

        bus.on(events.SAVE, () => {
                let params = new URLSearchParams();
                params.append('monto_inicial', form.getField('monto_inicial').numberbox('getValue'));
                httpClient.post('cashbox/open', params).subscribe(
                    (response) => {
                        bus.emit(events.SUCCESS);
                    }
                );
            }
        );

        bus.on(events.CANCEL, () => this.dataGrid.reload());
    }

    private closeCashbox() {
        messenger.confirm(
            'Cerrar caja',
            '¿Está seguro de cerrar la caja?',
            (r: boolean) => {
                if (r) {
                    let cashboxId = this.dataGrid.data['header']['caja_id'];
                    const params = new URLSearchParams();
                    params.append('estado', '2');
                    params.append('cierre', new Date().toISOString());
                    httpClient.put<any>('cashbox/edit/' + cashboxId, params).subscribe(
                        (response) => {
                            messenger.show('Cerrar caja', response.data.message);
                            this.dataGrid.reload();
                        }
                    )
                }
            }
        );
    }
}