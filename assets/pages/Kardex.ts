import {Field} from "../Field";
import DataGrid from "../DataGrid";
import {FieldBuilder} from "../FieldBuilder";
import {FormType} from "../FormType";

export default class Kardex {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;

    static readonly EVENTS = {
        ADD: 'local:add',
        EDIT: 'local:edit',
        DELETE: 'local:delete',
        SAVE: 'local:save',
        CANCEL: 'local:cancel',
        SUCCESS: 'local:success',
        LOADING: 'local:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .type(FormType.NONE)
            .name('producto_id')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('producto')
            .label('Producto')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('saldo')
            .label('Saldo inicial')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('ingresos')
            .label('Ingreso')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('salidas')
            .label('Salida')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('stock')
            .label('Saldo final')
            .align('right')
            .build());
    };

    render() {
        this.renderDataGrid();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Kardex',
            url: 'kardex/get',
            events: Kardex.EVENTS,
            subGrid: {
                url: 'kardex/detail/',
                columns: [
                    {
                        field: 'fecha',
                        title: 'Fecha',
                        align: 'center',
                        width: 50
                    }, {
                        field: 'tipo_id',
                        title: 'Tipo',
                        formatter: this.kindFormatter,
                        align: 'center',
                        width: 50
                    }, {
                        field: 'almacen',
                        title: 'Almacen',
                        width: 100
                    }, {
                        field: 'operacion',
                        title: 'Operacion',
                        width: 100
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        align: 'center',
                        width: 50
                    }
                ]
            }
        });
    }

    kindFormatter(value, row) {
        if (value === null) {
            value = -1
        }
        if (value === 'INGRESO') {
            return '<span class="badge badge-pill badge-success">INGRESO</span>';
        } else if (value === 'SALIDA') {
            return '<span class="badge badge-pill badge-danger">SALIDA</span>';
        } else {
            return '';
        }
    }
}