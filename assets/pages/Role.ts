import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";
import {Drawer} from "../Drawer";
import {bus} from "../EventBus";
import {Action} from "../Action";
import axios from "axios";

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

    constructor(container) {
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
                        bus.emit(new Action('permission:load', row));
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

        this.form = new Form(this.dialog.id, this.fields, {
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

        let permissionsDialog = new Dialog(drawer.id, {
            width: '100%',
            height: window.innerHeight,
            maxHeight: window.innerHeight,
            modal: false,
            events: EVENTS,
        });

        let container = document.getElementById(permissionsDialog.id);
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
            (action: Action) => {
                let row = action.data;
                permissionsDialog.show('Permisos del rol ' + row.descripcion);
                drawer.show();
                permissionsTree.tree('loadData', []);
                bus.emit(new Action(EVENTS.LOADING, true));
                axios.get('role-menu/get', {params: {roleId: row.rol_id}})
                    .then(function (response) {
                        permissionsTree.tree('loadData', response.data);
                        bus.emit(new Action(EVENTS.LOADING, false));
                    })
                    .catch(function (error) {
                        SharedUtils.showMessage('Error', error);
                        bus.emit(new Action(EVENTS.LOADING, false));
                    })
            }
        )

        bus.on(
            action => action.name === EVENTS.SAVE,
            (action) => {
                let row = this.dataGrid.selectedRow;
                let selected = permissionsTree.tree('getChecked', ['checked', 'indeterminate']);
                let permissions = selected.map((obj) => obj.id);
                axios.post('role-menu/add', {roleId: row.rol_id, permissions: permissions})
                    .then(function (response) {
                        SharedUtils.showMessage('Info', response.data.msg);
                        permissionsDialog.hide();
                        drawer.hide();
                        bus.emit(new Action(EVENTS.SUCCESS, true));
                    })
                    .catch(function (error) {
                        SharedUtils.showMessage('Error', error);
                    })
            }
        )
    }
}