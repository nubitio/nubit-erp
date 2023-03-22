import DataGrid from "../core/datagrid/DataGrid";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {Constants} from "../core/common/Constants";
import {SharedUtils} from "../core/utils/SharedUtils";

export default class CashboxConsult {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;

    static readonly ROUTES = {}

    static readonly EVENTS = {}

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
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

        this.detailFields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('tipo_id')
            .label('Tipo')
            .formatter(this.typeFormatter)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('descripcion')
            .label('Descripcion')
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('referencia')
            .label('Observacion')
            .width(100)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('monto')
            .label('Monto')
            .align('right')
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
            detailUrl: 'cashbox/{id}/details',
            detailFields: this.detailFields
        });
    }

    private statusFormatter(value: number) {
        value = Number(value);
        let text = '';
        if (value === 1) {
            text = '<span class="badge rounded-pill bg-success">ACTIVO</span>';
        } else {
            text = '<span class="badge rounded-pill bg-info">CERRADO</span>';
        }
        return text;
    }

    private typeFormatter(value: number) {
        value = Number(value);
        let text = '';
        if (value === 1) {
            text = '<span class="badge rounded-pill bg-success">INGRESO</span>';
        } else {
            text = '<span class="badge rounded-pill bg-danger">EGRESO</span>';
        }
        return text;
    }
}