import DataGrid from "../DataGrid";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {Constants} from "../Constants";
import {SharedUtils} from "../utils/SharedUtils";

export default class CashboxConsult {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;

    static readonly ROUTES = {}

    static readonly EVENTS = {}

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.HIDDEN)
            .name('caja_id')
            .label('Caja')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('apertura')
            .label('Apertura')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cierre')
            .label('Cierre')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('usuario')
            .label('Cajero')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('saldo_inicial')
            .label('Saldo inicial')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('saldo_final')
            .label('Saldo final')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Estado')
            .align('center')
            .formatter(this.statusFormatter)
            .build());
    };

    render() {
        this.renderDataGrid();
    }

    renderDataGrid() {
        let toolbar = [
            {
                text: 'Exportar',
                iconCls: Constants.ICON_PDF,
                handler: () => {
                    let row: any = this.dataGrid.selectedRow;
                    if (row) {
                        window.open('cashbox/export/' + row.caja_id);
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro');
                    }
                }
            },
            {
                text: 'Reporte',
                iconCls: Constants.ICON_PDF,
                handler: () => {
                    let row: any = this.dataGrid.selectedRow;
                    if (row) {
                        window.open('cashbox/report/' + row.caja_id);
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro');
                    }
                }
            },
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Cashbox Report',
            url: 'cashbox/get',
            routes: CashboxConsult.ROUTES,
            events: CashboxConsult.EVENTS,
            toolbar: toolbar,
            subGrid: {
                url: 'api/cashbox/detail/',
                columns: [
                    {field: 'tipo_id', title: 'Tipo', formatter: this.typeFormatter},
                    {field: 'descripcion', title: 'Concepto'},
                    {field: 'referencia', title: 'Observaciones', width: 100},
                    {field: 'monto', title: 'Monto', align: 'right'},
                ]
            }
        });
    }

    private statusFormatter(value, row, index) {
        value = Number(value);
        if (value === 1) {
            value = '<span class="badge rounded-pill bg-success">ACTIVO</span>';
        } else {
            value = '<span class="badge rounded-pill bg-info">CERRADO</span>';
        }
        return value;
    }

    private typeFormatter(value, row, index) {
        value = Number(value);
        if (value === 1) {
            value = '<span class="badge rounded-pill bg-success">INGRESO</span>';
        } else {
            value = '<span class="badge rounded-pill bg-danger">EGRESO</span>';
        }
        return value;
    }
}