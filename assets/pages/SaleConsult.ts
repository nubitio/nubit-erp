import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {FormType} from "../FormType";
import Dialog from "../Dialog";
import Form from "../Form";
import {bus} from "../EventBus";
import {Action} from "../Action";

export default class SaleConsult {
    private readonly container: HTMLElement;
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

    constructor(container: HTMLElement) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields = [
            new FieldBuilder()
                .type(FormType.ENUM)
                .name('report')
                .label('Reporte')
                .data([
                    {value: 'sale-by-product', text: 'Ventas por producto'},
                    {value: 'detailed-sales', text: 'Ventas detalladas'},
                    {value: 'accounting-report', text: 'Ventas contable'},
                    {value: 'voided-sales', text: 'Ventas anuladas'},
                    {value: 'credit-notes', text: 'Notas de credito'},
                ])
                .required(true)
                .build(),
            new FieldBuilder()
                .type(FormType.DATE)
                .name('date_from')
                .label('Desde')
                .defaultValue('Today')
                .required(true)
                .build(),
            new FieldBuilder()
                .type(FormType.DATE)
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
            closable: false,
            positiveText: 'Generar',
        });

        let form = new Form(dialog.id, this.fields, {
            events: SaleConsult.EVENTS,
            preventDefault: true,
        });

        bus.on(
            action => action.name === SaleConsult.EVENTS.SAVE,
            action => {
                let data: any = form.getData();
                data = data.replace(/_/g, '-');
                window.open(`/sale/consult?${data}`);
                form.reset();
            }
        );

        bus.emit(new Action(SaleConsult.EVENTS.ADD, {title: 'Reporte de ventas'}));
    }
}