import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Sale {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'sale/add',
        EDIT: 'sale/edit/',
        DELETE: 'sale/delete/',
    }

    constructor(container, options) {
        this.container = container;
        this.dataGrid = null;
        this.dialog = null;
        this.form = null;
        this.options = options;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.HIDDEN)
            .name('movimiento_id')
            .label('Movimiento id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('operacion_id')
            .label('Operacion id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('almacen_id')
            .label('Almacen id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cliente_id')
            .label('Cliente id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_id')
            .label('Tipo id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('proveedor_id')
            .label('Proveedor id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_pago')
            .label('Tipo pago')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_tarjeta')
            .label('Tipo tarjeta')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('serie')
            .label('Serie')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('numero')
            .label('Numero')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descuento')
            .label('Descuento')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('glosa')
            .label('Glosa')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('orden_id')
            .label('Orden id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('has_invoice')
            .label('Has invoice')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('is_consume')
            .label('Is consume')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('caja_id')
            .label('Caja id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Estado')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('igv')
            .label('Igv')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('recargo_consumo')
            .label('Recargo consumo')
            .build());

    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Ventas',
            url: 'sale/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}