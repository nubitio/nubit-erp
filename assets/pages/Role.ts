import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Drawer} from "../core/drawer/Drawer";
import {bus} from "../core/event/EventBus";
import {Event} from "../core/event/Event";
import {httpClient} from "../core/utils/HttpClient";

export default class Role {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'role/add',
        EDIT: 'role/edit/',
        DELETE: 'role/delete/',
    }

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
            .type(FormType.NONE)
            .name('rol_id')
            .label('Rol id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
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
                        bus.emit(new Event('permission:load', row));
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro');
                    }
                }
            }
        ];

        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Role',
            url: 'role/get',
            toolbar: toolbar,
            routes: Role.ROUTES,
            events: Role.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Role.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Role.ROUTES,
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

        bus.on(
            action => action.name === 'permission:load',
            (action: Event) => {
                let row = action.data;
                permissionsDialog.show('Permisos del rol ' + row.descripcion);
                drawer.show();
                permissionsTree.tree('loadData', []);
                bus.emit(new Event(EVENTS.LOADING, true));
                httpClient.get<any>('role-menu/get', {params: {roleId: row['rol_id']}}).subscribe(
                    response => {
                        permissionsTree.tree('loadData', response);
                        bus.emit(new Event(EVENTS.LOADING, false));
                    }
                )
            }
        )

        bus.on(
            action => action.name === EVENTS.SAVE,
            (action) => {
                let row = this.dataGrid.selectedRow;
                let selected = permissionsTree.tree('getChecked', ['checked', 'indeterminate']);
                let permissions = selected.map((obj: any) => obj.id);
                httpClient.post<any>('role-menu/add', {roleId: row.rol_id, permissions: permissions}).subscribe(
                    response => {
                        SharedUtils.showMessage('Info', response.msg);
                        permissionsDialog.hide();
                        drawer.hide();
                        bus.emit(new Event(EVENTS.SUCCESS, true));
                    }
                )
            }
        )
    }
}