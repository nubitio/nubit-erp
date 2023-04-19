import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {FieldType} from "../core/field/FieldType";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {bus} from "../core/event/EventBus";

export default class SaleConsult {
    private readonly container: string;
    private fields: Field[]

    static readonly EVENTS = {
        ADD: 'sale-consult:add',
        EDIT: 'sale-consult:edit',
        DELETE: 'sale-consult:delete',
        SAVE: 'sale-consult:save',
        CANCEL: 'sale-consult:cancel',
        SUCCESS: 'sale-consult:success',
        LOADING: 'sale-consult:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields = [
            new FieldBuilder()
                .type(FieldType.ENUM)
                .name('report')
                .label('Reporte')
                .data([
                    {value: 'sale-by-product', text: 'Ventas por producto'},
                    {value: 'detailed-sales', text: 'Ventas detalladas'},
                    {value: 'accounting-report', text: 'Ventas contable'},
                    {value: 'voided-sales', text: 'Comunicaciones de baja'},
                    {value: 'credit-notes', text: 'Notas de credito valorizado'},
                    {value: 'sale-notes', text: 'Reporte de notas de pedido'},
                ])
                .required(true)
                .build(),
            new FieldBuilder()
                .type(FieldType.DATE)
                .name('date_from')
                .label('Desde')
                .defaultValue('Today')
                .required(true)
                .build(),
            new FieldBuilder()
                .type(FieldType.DATE)
                .name('date_to')
                .label('Hasta')
                .defaultValue('Today')
                .required(true)
                .build(),
        ];
    }

    render() {
        let dialog = new Dialog(this.container, {
            events: SaleConsult.EVENTS,
            positiveText: 'Generar',
        });

        let form = new Form(dialog.id ?? '', this.fields, {
            events: SaleConsult.EVENTS,
            preventDefault: true,
        });

        bus.on(SaleConsult.EVENTS.SAVE, () => {
                let data: any = form.getData();
                data = data.replace(/_/g, '-');
                window.open(`/sale/consult?${data}`);
                form.reset();
            }
        );

        bus.on(SaleConsult.EVENTS.CANCEL, () => {
            location.href = '/';
        });

        bus.emit(SaleConsult.EVENTS.ADD, {title: 'Reporte de ventas'});
    }
}