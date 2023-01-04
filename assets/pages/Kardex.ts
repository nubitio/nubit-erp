import {Field} from "../core/form/Field";
import DataGrid from "../core/datagrid/DataGrid";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {FormType} from "../core/form/FormType";

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

    constructor(container: string) {
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

    kindFormatter(value: string | null) {
        if (value === 'INGRESO') {
            return '<span class="badge badge-pill badge-success">INGRESO</span>';
        } else if (value === 'SALIDA') {
            return '<span class="badge badge-pill badge-danger">SALIDA</span>';
        } else {
            return '';
        }
    }
}