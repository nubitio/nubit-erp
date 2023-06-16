import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";
import {SharedUtils} from "../core/utils/SharedUtils";

export default class UnitOfMeasure {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'unit-of-measures';

    static readonly EVENTS = {
        ADD: 'unit-of-measure:add',
        EDIT: 'unit-of-measure:edit',
        DELETE: 'unit-of-measure:delete',
        SAVE: 'unit-of-measure:save',
        CANCEL: 'unit-of-measure:cancel',
        SUCCESS: 'unit-of-measure:success',
        LOADING: 'unit-of-measure:loading',
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
            .name('unidad_id')
            .label('Unidad id')
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('name')
            .label('Nombre')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENUM)
            .name('type_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'Unidad'},
                {value: 2, text: 'Peso'},
                {value: 3, text: 'Volumen'},
                {value: 4, text: 'Longitud'},
                {value: 5, text: 'Tiempo'},
            ])
            .formatter((value) => {
                switch (value) {
                    case 1:
                        return 'Unidad';
                    case 2:
                        return 'Peso';
                    case 3:
                        return 'Volumen';
                    case 4:
                        return 'Longitud';
                    case 5:
                        return 'Tiempo';
                    default:
                        return '';
                }
            })
            .defaultValue(1)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('ratio')
            .label('Ratio')
            .precision(5)
            .defaultValue(1)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('is_reference')
            .label('Es referencia')
            .formatter(SharedUtils.booleanFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('active')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'UnitOfMeasure',
            url: UnitOfMeasure.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: UnitOfMeasure.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: UnitOfMeasure.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: UnitOfMeasure.API_URL,
            events: UnitOfMeasure.EVENTS,
        });
    }
}
