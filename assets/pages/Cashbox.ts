import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {messenger} from "../utils/Messenger";
import {httpClient} from "../utils/HttpClient";
import {bus} from "../EventBus";
import {Action} from "../Action";

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

    constructor(container) {
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
            onLoadSuccess: (data) => {
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

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
        });
    }

    private typeFormatter(value, row, index) {
        value = Number(value);
        if (value === 1) {
            value = '<span class="badge rounded-pill bg-success">INGRESO</span>';
        } else if (value === 2) {
            value = '<span class="badge rounded-pill bg-danger">EGRESO</span>';
        } else {
            value = '';
        }
        return value;
    }

    private checkCashbox(): void {
        let cashboxId = this.dataGrid.data['header']['caja_id'];
        if (cashboxId === undefined) {
            bus.emit(new Action('cashbox-open:add'));
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
        let form = new Form(dialog.id, [
            new FieldBuilder()
                .type(FormType.CURRENCY)
                .name('monto_inicial')
                .label('Monto inicial')
                .align('right')
                .required(true)
                .defaultValue(initialAmount)
                .build(),
        ]);

        bus.on(
            action => action.name === events.SAVE,
            (action) => {
                let params = new URLSearchParams();
                params.append('monto_inicial', form.getField('monto_inicial').numberbox('getValue'));
                httpClient.post('cashbox/open', params).subscribe(
                    (response) => {
                        bus.emit(new Action(events.SUCCESS));
                    }
                );
            }
        );

        bus.on(
            action => action.name === events.CANCEL,
            (action) => {
                this.dataGrid.reload();
            }
        );
    }

    private closeCashbox() {
        messenger.confirm(
            'Cerrar caja',
            '¿Está seguro de cerrar la caja?',
            (r) => {
                if (r) {
                    let cashboxId = this.dataGrid.data['header']['caja_id'];
                    const params = new URLSearchParams();
                    params.append('estado', '2');
                    httpClient.put<any>('cashbox/edit/' + cashboxId, params).subscribe(
                        (response) => {
                            messenger.show('Cerrar caja', response.message);
                            this.dataGrid.reload();
                        }
                    )
                }
            }
        );
    }
}