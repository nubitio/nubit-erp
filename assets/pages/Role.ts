import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Drawer} from "../core/drawer/Drawer";
import {bus} from "../core/event/EventBus";
import {httpClient} from "../core/http/HttpClient";
import {Constants} from "../core/common/Constants";

export default class Role {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'roles';

    static readonly EVENTS = {
        ADD: 'role:add',
        EDIT: 'role:edit',
        DELETE: 'role:delete',
        SAVE: 'role:save',
        CANCEL: 'role:cancel',
        SUCCESS: 'role:success',
        LOADING: 'role:loading',
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
            .name('rol_id')
            .label('Rol id')
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .required(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
        this.configurePermissions();
    }

    renderDataGrid() {
        let toolbar = [
            {
                text: 'Permisos',
                iconCls: 'fas fa-user-shield fa-1x',
                handler: () => {
                    let row = this.dataGrid.selectedRow;
                    if (row) {
                        bus.emit('permission:load', row);
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro');
                    }
                }
            }
        ];

        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Role',
            url: Role.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Role.EVENTS,
            toolbar: toolbar
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Role.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Role.API_URL,
            events: Role.EVENTS,
        });
    }

    private configurePermissions() {
        let EVENTS = {
            ADD: 'permission:add',
            EDIT: 'permission:edit',
            DELETE: 'permission:delete',
            SAVE: 'permission:save',
            CANCEL: 'permission:cancel',
            SUCCESS: 'permission:success',
            LOADING: 'permission:loading',
        }

        let drawer = new Drawer({
            events: EVENTS,
        });

        let permissionsDialog = new Dialog(drawer.id ?? '', {
            width: '100%',
            height: window.innerHeight,
            maxHeight: window.innerHeight,
            modal: false,
            inline: true,
            events: EVENTS,
        });

        let container = document.getElementById(<string>permissionsDialog.id);

        if (!container) {
            return;
        }

        let permissionsList = document.createElement('ul');
        permissionsList.setAttribute('id', 'permissions');
        container.appendChild(permissionsList);

        let permissionsTree = $('#permissions');

        permissionsTree.tree({
            checkbox: true,
            lines: true,
        });

        bus.on<any>('permission:load', (payload) => {
                let row = payload;
                permissionsDialog.show('Permisos del rol ' + row.descripcion);
                drawer.show();
                permissionsTree.tree('loadData', []);
                bus.emit(EVENTS.LOADING, true);
                httpClient.get<any>('role-menu/get', {params: {roleId: row['rol_id']}}).subscribe(
                    response => {
                        permissionsTree.tree('loadData', response.data);
                        bus.emit(EVENTS.LOADING, false);
                    }
                )
            }
        )

        bus.on(EVENTS.SAVE, () => {
                let row = this.dataGrid.selectedRow;
                let selected = permissionsTree.tree('getChecked', ['checked', 'indeterminate']);
                let permissions = selected.map((obj: any) => obj.id);
                httpClient.post<any>('role-menu/add', {roleId: row['rol_id'], permissions: permissions}).subscribe(
                    response => {
                        SharedUtils.showMessage('Info', response.data.msg);
                        permissionsDialog.hide();
                        drawer.hide();
                        bus.emit(EVENTS.SUCCESS, true);
                    }
                )
            }
        )
    }
}
