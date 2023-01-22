import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import EasyComponent from "../core/common/EasyComponent";

export default class Branch extends EasyComponent {
    private readonly container: string;
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/branches';

    static readonly EVENTS = {
        ADD: 'branch:add',
        EDIT: 'branch:edit',
        DELETE: 'branch:delete',
        SAVE: 'branch:save',
        CANCEL: 'branch:cancel',
        SUCCESS: 'branch:success',
        LOADING: 'branch:loading',
    }

    constructor(container: string) {
        super();
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('local_id')
            .label('Local id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('empresa')
            .label('Empresa')
            .url('api/company')
            .valueField('empresa_id')
            .textField('razon_social')
            .required(true)
            .formatter((value: any, row: any) => value.razon_social)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('direccion')
            .label('Direccion')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('telefono')
            .label('Telefono')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Branch',
            url: Branch.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Branch.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Branch.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Branch.API_URL,
            events: Branch.EVENTS,
        });
    }
}